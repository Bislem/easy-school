<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnnualLeave;
use App\Models\CompanySetting;
use App\Models\Staff;
use App\Services\AnnualLeaveService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AnnualLeavesController extends Controller
{
    public function __construct(private AnnualLeaveService $service) {}

    public function store(Request $request, Staff $staff): RedirectResponse
    {
        Gate::authorize('update', $staff);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['days', 'full_month'])],
            'month' => ['nullable', 'required_if:mode,full_month', 'date_format:Y-m'],
            'starts_at' => ['nullable', 'required_if:mode,days', 'date'],
            'ends_at' => ['nullable', 'required_if:mode,days', 'date', 'after_or_equal:starts_at'],
            'reason' => ['nullable', 'string', 'max:3000'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        [$start, $end, $days] = $this->period($data);
        $this->service->assertCanReserve($staff, $start, $end, $days);
        DB::transaction(function () use ($request, $staff, $data, $start, $end, $days) {
            $leave = $staff->annualLeaves()->create([...$data, 'starts_at' => $start, 'ends_at' => $end, 'days' => $days, 'status' => 'pending', 'requested_at' => now(), 'created_by' => $request->user()->id]);
            $this->event($leave, $request, 'created', null, 'pending', 'Demande enregistrée');
        });

        return back()->with('success', 'Demande de congé enregistrée.');
    }

    public function approve(Request $request, Staff $staff, AnnualLeave $annualLeave): RedirectResponse
    {
        $this->guard($staff, $annualLeave, ['pending']);
        $this->service->assertCanReserve($staff, $annualLeave->starts_at, $annualLeave->ends_at, (float) $annualLeave->days, $annualLeave);
        $annualLeave->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        $this->event($annualLeave, $request, 'approved', 'pending', 'approved');

        return back()->with('success', 'Congé approuvé.');
    }

    public function reject(Request $request, Staff $staff, AnnualLeave $annualLeave): RedirectResponse
    {
        $this->guard($staff, $annualLeave, ['pending']);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $annualLeave->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => $request->user()->id, ...$data]);
        $this->event($annualLeave, $request, 'rejected', 'pending', 'rejected', $data['rejection_reason']);

        return back()->with('success', 'Demande refusée.');
    }

    public function cancel(Request $request, Staff $staff, AnnualLeave $annualLeave): RedirectResponse
    {
        $this->guard($staff, $annualLeave, ['pending', 'approved']);
        $from = $annualLeave->status;
        $annualLeave->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id]);
        $this->event($annualLeave, $request, 'cancelled', $from, 'cancelled');

        return back()->with('success', 'Congé annulé et solde libéré.');
    }

    public function complete(Request $request, Staff $staff, AnnualLeave $annualLeave): RedirectResponse
    {
        $this->guard($staff, $annualLeave, ['approved']);
        $data = $request->validate(['actual_return_date' => ['required', 'date', 'after:'.$annualLeave->starts_at->format('Y-m-d')]]);
        $annualLeave->update(['status' => 'taken', ...$data]);
        $this->event($annualLeave, $request, 'returned', 'approved', 'taken', 'Reprise le '.$data['actual_return_date']);

        return back()->with('success', 'Reprise de travail enregistrée.');
    }

    public function print(Request $request, Staff $staff, AnnualLeave $annualLeave): Response
    {
        Gate::authorize('view', $staff);
        abort_unless($annualLeave->staff_id === $staff->id, 404);
        $type = $request->validate(['document' => ['required', Rule::in(['request', 'authorization', 'return'])]])['document'];
        abort_if($type === 'authorization' && ! in_array($annualLeave->status, ['approved', 'taken']), 422);
        abort_if($type === 'return' && $annualLeave->status !== 'taken', 422);
        $annualLeave->load(['staff.employeeType', 'creator:id,name', 'approver:id,name']);

        return Pdf::loadView('admin.hr.annual-leave-document', ['leave' => $annualLeave, 'type' => $type, 'school' => CompanySetting::current(), 'summary' => $this->service->summary($staff, $annualLeave->ends_at)])->download("conge-{$type}-{$staff->employee_code}-{$annualLeave->id}.pdf");
    }

    private function guard(Staff $staff, AnnualLeave $leave, array $statuses): void
    {
        Gate::authorize('update', $staff);
        abort_unless($leave->staff_id === $staff->id, 404);
        abort_unless(in_array($leave->status, $statuses), 422, 'Transition de statut non autorisée.');
    }

    private function period(array $data): array
    {
        if ($data['mode'] === 'full_month') {
            $start = Carbon::createFromFormat('!Y-m', $data['month'])->startOfMonth();

            return [$start, $start->copy()->endOfMonth(), 30.0];
        }
        $start = Carbon::parse($data['starts_at'])->startOfDay();
        $end = Carbon::parse($data['ends_at'])->startOfDay();

        return [$start, $end, (float) ($start->diffInDays($end) + 1)];
    }

    private function event(AnnualLeave $leave, Request $request, string $event, ?string $from, string $to, ?string $notes = null): void
    {
        $leave->events()->create(['actor_id' => $request->user()->id, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes]);
    }
}
