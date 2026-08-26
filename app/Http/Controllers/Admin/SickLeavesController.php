<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\SickLeave;
use App\Models\Staff;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SickLeavesController extends Controller
{
    public function store(Request $request, Staff $staff): RedirectResponse
    {
        Gate::authorize('update', $staff);
        $data = $request->validate([
            'category' => ['required', Rule::in(['illness', 'work_accident', 'hospitalization', 'medical_recovery'])],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'certificate_received' => ['required', 'boolean'], 'certificate_reference' => ['nullable', 'string', 'max:150'],
            'certificate_issued_at' => ['nullable', 'date'], 'health_professional' => ['nullable', 'string', 'max:255'],
            'administrative_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        if ($staff->hire_date && $start->lt($staff->hire_date)) {
            throw ValidationException::withMessages(['starts_at' => "Le congé ne peut pas précéder la date d'embauche."]);
        }
        $overlap = $staff->sickLeaves()->whereIn('status', ['pending', 'approved', 'taken'])->whereDate('starts_at', '<=', $end)->whereDate('ends_at', '>=', $start)->exists()
            || $staff->annualLeaves()->whereIn('status', ['pending', 'approved', 'taken'])->whereDate('starts_at', '<=', $end)->whereDate('ends_at', '>=', $start)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Cette période chevauche un autre congé actif.']);
        }
        $leave = $staff->sickLeaves()->create([...$data, 'days' => $start->diffInDays($end) + 1, 'status' => 'pending', 'requested_at' => now(), 'created_by' => $request->user()->id]);
        $this->event($leave, $request, 'created', null, 'pending', 'Arrêt maladie enregistré');

        return back()->with('success', 'Congé maladie enregistré.');
    }

    public function approve(Request $request, Staff $staff, SickLeave $sickLeave): RedirectResponse
    {
        $this->guard($staff, $sickLeave, ['pending']);
        $sickLeave->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        $this->event($sickLeave, $request, 'approved', 'pending', 'approved');

        return back()->with('success', 'Congé maladie validé.');
    }

    public function reject(Request $request, Staff $staff, SickLeave $sickLeave): RedirectResponse
    {
        $this->guard($staff, $sickLeave, ['pending']);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $sickLeave->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => $request->user()->id, ...$data]);
        $this->event($sickLeave, $request, 'rejected', 'pending', 'rejected', $data['rejection_reason']);

        return back()->with('success', 'Congé maladie refusé.');
    }

    public function cancel(Request $request, Staff $staff, SickLeave $sickLeave): RedirectResponse
    {
        $this->guard($staff, $sickLeave, ['pending', 'approved']);
        $from = $sickLeave->status;
        $sickLeave->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id]);
        $this->event($sickLeave, $request, 'cancelled', $from, 'cancelled');

        return back()->with('success', 'Congé maladie annulé.');
    }

    public function complete(Request $request, Staff $staff, SickLeave $sickLeave): RedirectResponse
    {
        $this->guard($staff, $sickLeave, ['approved']);
        $data = $request->validate(['actual_return_date' => ['required', 'date', 'after:'.$sickLeave->starts_at->format('Y-m-d')], 'fit_to_return_confirmed' => ['required', 'boolean']]);
        $sickLeave->update(['status' => 'taken', ...$data]);
        $this->event($sickLeave, $request, 'returned', 'approved', 'taken', 'Reprise le '.$data['actual_return_date']);

        return back()->with('success', 'Reprise après maladie enregistrée.');
    }

    public function print(Request $request, Staff $staff, SickLeave $sickLeave): Response
    {
        Gate::authorize('view', $staff);
        abort_unless($sickLeave->staff_id === $staff->id, 404);
        $type = $request->validate(['document' => ['required', Rule::in(['declaration', 'decision', 'return'])]])['document'];
        abort_if($type === 'decision' && ! in_array($sickLeave->status, ['approved', 'taken']), 422);
        abort_if($type === 'return' && $sickLeave->status !== 'taken', 422);
        $sickLeave->load(['staff.employeeType', 'creator:id,name', 'approver:id,name']);

        return Pdf::loadView('admin.hr.sick-leave-document', ['leave' => $sickLeave, 'type' => $type, 'school' => CompanySetting::current()])->download("conge-maladie-{$type}-{$staff->employee_code}-{$sickLeave->id}.pdf");
    }

    private function guard(Staff $staff, SickLeave $leave, array $statuses): void
    {
        Gate::authorize('update', $staff);
        abort_unless($leave->staff_id === $staff->id, 404);
        abort_unless(in_array($leave->status, $statuses), 422, 'Transition non autorisée.');
    }

    private function event(SickLeave $leave, Request $request, string $event, ?string $from, string $to, ?string $notes = null): void
    {
        $leave->events()->create(['actor_id' => $request->user()->id, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes]);
    }
}
