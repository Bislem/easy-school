<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Enums\PrivateSchoolCampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\PrivateSchoolInscriptionCampaign;
use App\Models\SchoolLevel;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PrivateSchoolCampaignsController extends Controller
{
    public function index(Request $request): Response
    {
        $campaigns = PrivateSchoolInscriptionCampaign::with(['academicYear:id,name,status', 'levels.level:id,name,code'])
            ->withCount('inscriptions')
            ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->latest()->paginate(12)->withQueryString();

        return Inertia::render('Admin/PrivateSchoolCampaigns/Index', [
            'campaigns' => $campaigns,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'status']),
            'activeAcademicYearId' => AcademicYear::where('status', AcademicYearStatus::ACTIVE)->value('id'),
            'levels' => SchoolLevel::where('is_active', true)->with('cycle:id,name')->orderBy('sort_order')->get(['id', 'school_cycle_id', 'name', 'code']),
            'filters' => $request->only(['search', 'academic_year_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $this->validated($request);
            $levels = $data['levels'];
            unset($data['levels']);
            $campaign = PrivateSchoolInscriptionCampaign::create($data);
            $this->syncLevels($campaign, $levels);
        });

        return back()->with('success', 'Campagne d’inscription créée.');
    }

    public function update(Request $request, PrivateSchoolInscriptionCampaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign): void {
            $data = $this->validated($request);
            $levels = $data['levels'];
            unset($data['levels']);
            if ($campaign->academic_year_id !== (int) $data['academic_year_id'] && $campaign->inscriptions()->exists()) {
                throw ValidationException::withMessages(['academic_year_id' => 'L’année scolaire ne peut plus être modifiée après réception d’une demande.']);
            }
            $usedIds = $campaign->inscriptions()->pluck('school_level_id');
            $submittedIds = collect($levels)->pluck('school_level_id');
            if ($usedIds->diff($submittedIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['levels' => 'Un niveau ayant déjà reçu des demandes ne peut pas être retiré. Fermez-le plutôt.']);
            }
            $campaign->update($data);
            $this->syncLevels($campaign, $levels);
        });

        return back()->with('success', 'Campagne mise à jour.');
    }

    public function status(Request $request, PrivateSchoolInscriptionCampaign $campaign): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(PrivateSchoolCampaignStatus::class)]]);
        if ($data['status'] === PrivateSchoolCampaignStatus::OPEN->value && ! $campaign->levels()->where('is_open', true)->exists()) {
            throw ValidationException::withMessages(['status' => 'Ouvrez au moins un niveau avant d’ouvrir la campagne.']);
        }
        $campaign->update($data);

        return back()->with('success', 'Statut de la campagne mis à jour.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', TenantRule::exists('academic_years')],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date'], 'status' => ['required', Rule::enum(PrivateSchoolCampaignStatus::class)],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.school_level_id' => ['required', 'integer', 'distinct', TenantRule::exists('school_levels')],
            'levels.*.max_places' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'levels.*.is_open' => ['required', 'boolean'],
        ]);
    }

    private function syncLevels(PrivateSchoolInscriptionCampaign $campaign, array $levels): void
    {
        $ids = [];
        foreach ($levels as $level) {
            $record = $campaign->levels()->updateOrCreate(['school_level_id' => $level['school_level_id']], ['max_places' => $level['max_places'] ?? null, 'is_open' => $level['is_open']]);
            $accepted = $record->acceptedInscriptions()->count();
            if ($record->max_places !== null && $record->max_places < $accepted) {
                throw ValidationException::withMessages(['levels' => "La capacité du niveau ne peut pas être inférieure aux {$accepted} inscriptions acceptées."]);
            }
            $ids[] = $record->id;
        }
        $campaign->levels()->whereNotIn('id', $ids)->delete();
    }
}
