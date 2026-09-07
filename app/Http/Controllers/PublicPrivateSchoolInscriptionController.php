<?php

namespace App\Http\Controllers;

use App\Models\PrivateSchoolInscription;
use App\Models\PrivateSchoolInscriptionCampaign;
use App\Services\PrivateSchoolApplicantMatcher;
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
        $campaign->load(['academicYear:id,name', 'levels' => fn ($query) => $query->where('is_open', true)->with('level.cycle')->withCount('acceptedInscriptions')]);

        return Inertia::render('Public/PrivateSchoolInscription', [
            'campaign' => $campaign,
            'isAvailable' => $campaign->acceptsRequests() && $campaign->levels->isNotEmpty(),
        ]);
    }

    public function store(Request $request, PrivateSchoolInscriptionCampaign $campaign, PrivateSchoolApplicantMatcher $matcher): RedirectResponse
    {
        app(TenantContext::class)->set($campaign->tenant_id);
        abort_unless($campaign->acceptsRequests(), 422, 'Cette campagne d’inscription est fermée.');
        $data = $request->validate([
            'parent_first_name' => ['required', 'string', 'max:100'], 'parent_last_name' => ['required', 'string', 'max:100'],
            'parent_email' => ['required', 'email', 'max:255'], 'parent_phone' => ['required', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'student_first_name' => ['required', 'string', 'max:100'], 'student_last_name' => ['required', 'string', 'max:100'],
            'student_email' => ['nullable', 'email', 'max:255'], 'student_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['required', 'date', 'before:today'], 'address' => ['nullable', 'string', 'max:255'],
            'campaign_level_id' => ['required', 'integer'],
        ]);

        $campaignLevel = $campaign->levels()->whereKey($data['campaign_level_id'])->where('is_open', true)->first();
        if (! $campaignLevel) {
            throw ValidationException::withMessages(['campaign_level_id' => 'Ce niveau n’est pas disponible pour cette campagne.']);
        }

        try {
            DB::transaction(function () use ($campaign, $campaignLevel, $data, $matcher): void {
                $parent = $matcher->parent(['first_name' => $data['parent_first_name'], 'last_name' => $data['parent_last_name'], 'email' => $data['parent_email'], 'phone' => $data['parent_phone'], 'relationship' => $data['relationship'] ?? null]);
                $student = $matcher->student(['first_name' => $data['student_first_name'], 'last_name' => $data['student_last_name'], 'email' => $data['student_email'] ?? null, 'phone' => $data['student_phone'] ?? null, 'birth_date' => $data['birth_date'], 'address' => $data['address'] ?? null, 'parent_phone' => $data['parent_phone']], $parent);
                $matcher->link($parent, $student);
                if (PrivateSchoolInscription::where('student_id', $student->id)->where('campaign_id', $campaign->id)->where('school_level_id', $campaignLevel->school_level_id)->exists()) {
                    throw ValidationException::withMessages(['campaign_level_id' => 'Une demande existe déjà pour cet élève, cette campagne et ce niveau.']);
                }
                PrivateSchoolInscription::create([
                    'academic_year_id' => $campaign->academic_year_id, 'campaign_id' => $campaign->id,
                    'campaign_level_id' => $campaignLevel->id, 'school_level_id' => $campaignLevel->school_level_id,
                    'student_id' => $student->id, 'parent_id' => $parent->id, 'status' => 'pending',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['campaign_level_id' => 'Une demande identique existe déjà.']);
        }

        return back()->with('private_school_inscription_submitted', true);
    }
}
