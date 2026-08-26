<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeHrRecord;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmployeeHrRecordsController extends Controller
{
    public function store(Request $request, Staff $staff): RedirectResponse
    {
        Gate::authorize('update', $staff);
        $categories = config('hr.record_categories');
        $category = $request->string('category')->toString();
        abort_unless(isset($categories[$category]), 422);
        $definition = $categories[$category];
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys($categories))], 'type' => ['required', Rule::in(array_keys($definition['types']))],
            'title' => ['required', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:150'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(array_keys($definition['statuses']))], 'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'], 'description' => ['nullable', 'string', 'max:10000'],
            'is_confidential' => ['required', 'boolean'], 'metadata' => ['nullable', 'array'], 'metadata.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $record = $staff->hrRecords()->create([...$data, 'created_by' => $request->user()->id]);
        $record->events()->create(['actor_id' => $request->user()->id, 'event' => 'created', 'to_status' => $record->status, 'notes' => 'Fiche créée']);

        return back()->with('success', $definition['label'].' ajouté(e).');
    }

    public function updateStatus(Request $request, Staff $staff, EmployeeHrRecord $hrRecord): RedirectResponse
    {
        $this->guard($staff, $hrRecord);
        $statuses = config("hr.record_categories.{$hrRecord->category}.statuses", []);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys($statuses))], 'notes' => ['nullable', 'string', 'max:2000']]);
        $from = $hrRecord->status;
        $hrRecord->update(['status' => $data['status']]);
        $hrRecord->events()->create(['actor_id' => $request->user()->id, 'event' => 'status_changed', 'from_status' => $from, 'to_status' => $data['status'], 'notes' => $data['notes'] ?? null]);

        return back()->with('success', 'Statut mis à jour.');
    }

    public function archive(Request $request, Staff $staff, EmployeeHrRecord $hrRecord): RedirectResponse
    {
        $this->guard($staff, $hrRecord);
        abort_if($hrRecord->archived_at, 422);
        $hrRecord->update(['archived_at' => now()]);
        $hrRecord->events()->create(['actor_id' => $request->user()->id, 'event' => 'archived', 'from_status' => $hrRecord->status, 'to_status' => $hrRecord->status, 'notes' => 'Fiche archivée']);

        return back()->with('success', 'Fiche archivée.');
    }

    private function guard(Staff $staff, EmployeeHrRecord $record): void
    {
        Gate::authorize('update', $staff);
        abort_unless($record->staff_id === $staff->id, 404);
    }
}
