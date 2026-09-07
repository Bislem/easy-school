<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrivateSchoolInscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\PrivateSchoolCampaignLevel;
use App\Models\PrivateSchoolInscription;
use App\Models\PrivateSchoolInscriptionCampaign;
use App\Models\SchoolLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PrivateSchoolInscriptionsController extends Controller
{
    public function index(Request $request): Response
    {
        $items = PrivateSchoolInscription::with(['academicYear:id,name', 'campaign:id,title', 'level:id,name,code', 'student:id,first_name,last_name,email,phone,birth_date,address', 'parent.user:id,email,phone'])
            ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where(fn ($q) => $q
                ->whereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('parent', fn ($p) => $p->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('school_level_id'), fn ($q) => $q->where('school_level_id', $request->integer('school_level_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(20)->withQueryString();

        $capacity = PrivateSchoolCampaignLevel::with(['campaign:id,title,academic_year_id', 'level:id,name'])
            ->when($request->filled('academic_year_id'), fn ($q) => $q->whereHas('campaign', fn ($campaign) => $campaign->where('academic_year_id', $request->integer('academic_year_id'))))
            ->when($request->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('school_level_id'), fn ($q) => $q->where('school_level_id', $request->integer('school_level_id')))
            ->withCount(['acceptedInscriptions'])->get();

        return Inertia::render('Admin/PrivateSchoolInscriptions/Index', [
            'inscriptions' => $items, 'capacity' => $capacity,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'campaigns' => PrivateSchoolInscriptionCampaign::orderByDesc('created_at')->get(['id', 'title', 'academic_year_id']),
            'levels' => SchoolLevel::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => collect(PrivateSchoolInscriptionStatus::cases())->pluck('value'),
            'filters' => $request->only(['search', 'academic_year_id', 'campaign_id', 'school_level_id', 'status']),
        ]);
    }

    public function updateStatus(Request $request, PrivateSchoolInscription $inscription): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(PrivateSchoolInscriptionStatus::class)], 'review_notes' => ['nullable', 'string', 'max:3000']]);
        DB::transaction(function () use ($data, $request, $inscription): void {
            $locked = PrivateSchoolInscription::query()->lockForUpdate()->findOrFail($inscription->id);
            if ($data['status'] === PrivateSchoolInscriptionStatus::ACCEPTED->value && $locked->status !== PrivateSchoolInscriptionStatus::ACCEPTED) {
                $level = PrivateSchoolCampaignLevel::query()->lockForUpdate()->findOrFail($locked->campaign_level_id);
                if ($level->max_places !== null && $level->acceptedInscriptions()->count() >= $level->max_places) {
                    throw ValidationException::withMessages(['status' => 'La capacité de ce niveau est atteinte. Placez la demande en liste d’attente.']);
                }
            }
            $locked->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });

        return back()->with('success', 'Statut de l’inscription mis à jour.');
    }
}
