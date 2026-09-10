<?php

namespace App\Http\Controllers;

use App\Models\PrivateSchoolInscription;
use App\Models\PrivateSchoolInscriptionCampaign;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicPrivateSchoolInscriptionController extends Controller
{
    public function show(PrivateSchoolInscriptionCampaign $campaign): Response
    {
        app(TenantContext::class)->set($campaign->tenant_id);
        $campaign->load(['tenant:id,name,logo,commune,wilaya', 'academicYear:id,name', 'levels' => fn ($query) => $query->where('is_open', true)->with('level.cycle')->withCount('acceptedInscriptions')]);

        return Inertia::render('Public/PrivateSchoolInscription', [
            'campaign' => $campaign,
            'isAvailable' => $campaign->acceptsRequests() && $campaign->levels->isNotEmpty(),
        ]);
    }

    public function store(Request $request, PrivateSchoolInscriptionCampaign $campaign): RedirectResponse
    {
        app(TenantContext::class)->set($campaign->tenant_id);
        abort_unless($campaign->acceptsRequests(), 422, 'Cette campagne d’inscription est fermée.');
        $legacyPayload = ! $request->has('children');
        if ($legacyPayload) {
            $request->merge(['children' => [[
                'first_name' => $request->input('student_first_name'),
                'last_name' => $request->input('student_last_name'),
                'email' => $request->input('student_email'),
                'phone' => $request->input('student_phone'),
                'birth_date' => $request->input('birth_date'),
                'address' => $request->input('address'),
                'campaign_level_id' => $request->input('campaign_level_id'),
            ]]]);
        }
        $data = $request->validate([
            'parent_first_name' => ['required', 'string', 'max:100'], 'parent_last_name' => ['required', 'string', 'max:100'],
            'parent_email' => ['required', 'email', 'max:255'], 'parent_phone' => ['required', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'children' => ['required', 'array', 'min:1', 'max:10'],
            'children.*.first_name' => ['required', 'string', 'max:100'], 'children.*.last_name' => ['required', 'string', 'max:100'],
            'children.*.email' => ['nullable', 'email', 'max:255'], 'children.*.phone' => ['nullable', 'string', 'max:50'],
            'children.*.birth_date' => ['required', 'date', 'before:today'], 'children.*.address' => ['nullable', 'string', 'max:255'],
            'children.*.campaign_level_id' => ['required', 'integer'],
        ]);

        $campaignLevels = $campaign->levels()->whereIn('id', collect($data['children'])->pluck('campaign_level_id'))->where('is_open', true)->get()->keyBy('id');
        foreach ($data['children'] as $index => $child) {
            if (! $campaignLevels->has((int) $child['campaign_level_id'])) {
                $key = $legacyPayload ? 'campaign_level_id' : "children.{$index}.campaign_level_id";
                throw ValidationException::withMessages([$key => 'Ce niveau n’est pas disponible pour cette campagne.']);
            }
        }

        try {
            DB::transaction(function () use ($campaign, $campaignLevels, $data, $legacyPayload): void {
                foreach ($data['children'] as $index => $child) {
                    $campaignLevel = $campaignLevels->get((int) $child['campaign_level_id']);
                    if ($this->applicantAlreadySubmitted($campaign, $campaignLevel->school_level_id, $child)) {
                        $key = $legacyPayload ? 'campaign_level_id' : "children.{$index}.campaign_level_id";
                        throw ValidationException::withMessages([$key => 'Une demande existe déjà pour cet élève, cette campagne et ce niveau.']);
                    }
                    PrivateSchoolInscription::create([
                        'academic_year_id' => $campaign->academic_year_id, 'campaign_id' => $campaign->id,
                        'campaign_level_id' => $campaignLevel->id, 'school_level_id' => $campaignLevel->school_level_id,
                        'applicant_data' => [
                            'parent' => ['first_name' => $data['parent_first_name'], 'last_name' => $data['parent_last_name'], 'email' => $data['parent_email'], 'phone' => $data['parent_phone'], 'relationship' => $data['relationship'] ?? null],
                            'student' => ['first_name' => $child['first_name'], 'last_name' => $child['last_name'], 'email' => $child['email'] ?? null, 'phone' => ($child['phone'] ?? null) ?: $data['parent_phone'], 'birth_date' => $child['birth_date'], 'address' => $child['address'] ?? null],
                        ],
                        'status' => 'pending',
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([$legacyPayload ? 'campaign_level_id' : 'children' => 'Une demande identique existe déjà.']);
        }

        return back()->with('private_school_inscription_submitted', count($data['children']));
    }

    private function applicantAlreadySubmitted(PrivateSchoolInscriptionCampaign $campaign, int $levelId, array $child): bool
    {
        $email = mb_strtolower((string) ($child['email'] ?? ''));

        return PrivateSchoolInscription::where('campaign_id', $campaign->id)
            ->where('school_level_id', $levelId)
            ->with('student:id,email,first_name,last_name,birth_date')
            ->get()
            ->contains(function (PrivateSchoolInscription $inscription) use ($child, $email): bool {
                $student = $inscription->applicant_data['student'] ?? null;
                if (! $student && $inscription->student) {
                    $student = $inscription->student->toArray();
                }
                if (! $student) {
                    return false;
                }
                if ($email !== '' && mb_strtolower((string) ($student['email'] ?? '')) === $email) {
                    return true;
                }

                return mb_strtolower((string) ($student['first_name'] ?? '')) === mb_strtolower($child['first_name'])
                    && mb_strtolower((string) ($student['last_name'] ?? '')) === mb_strtolower($child['last_name'])
                    && substr((string) ($student['birth_date'] ?? ''), 0, 10) === $child['birth_date'];
            });
    }
}
