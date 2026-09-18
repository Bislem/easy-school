<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, ReportCard};
use App\Services\ReportCardValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ReportCardDetailsController extends Controller
{
    public function show(ReportCard $reportCard): Response
    {
        $this->assertTenant($reportCard);
        $reportCard->load(['student', 'year', 'level', 'group', 'subjects']);

        $siblings = ReportCard::query()
            ->where('tenant_id', $reportCard->tenant_id)
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('school_group_id', $reportCard->school_group_id)
            ->where('period_key', $reportCard->period_key)
            ->with('student:id,first_name,last_name,registration_number')
            ->orderBy('student_id')
            ->get(['id', 'student_id']);
        $position = $siblings->search(fn (ReportCard $card) => $card->id === $reportCard->id);

        return Inertia::render('Admin/ReportCards/Show', [
            'reportCard' => $reportCard,
            'history' => AuditLog::with('user:id,name')->where('related_type', ReportCard::class)->where('related_id', $reportCard->id)->latest('occurred_at')->get(),
            'readiness' => app(ReportCardValidationService::class)->readiness($reportCard),
            'previousCard' => $position > 0 ? $siblings[$position - 1] : null,
            'nextCard' => $position !== false && $position < $siblings->count() - 1 ? $siblings[$position + 1] : null,
        ]);
    }

    public function update(Request $request, ReportCard $reportCard): RedirectResponse
    {
        $this->assertTenant($reportCard);
        abort_unless($reportCard->status === 'draft', 422, 'Seuls les bulletins au brouillon peuvent être modifiés.');
        $data = $request->validate([
            'teacher_comment' => ['nullable', 'string', 'max:5000'],
            'administration_comment' => ['nullable', 'string', 'max:5000'],
            'subjects' => ['present', 'array'],
            'subjects.*.id' => ['required', 'integer'],
            'subjects.*.appreciation' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($reportCard, $data): void {
            $card = ReportCard::whereKey($reportCard->id)->lockForUpdate()->firstOrFail();
            abort_unless($card->status === 'draft', 422, 'Le bulletin a été modifié par un autre utilisateur. Actualisez la page puis réessayez.');
            $old = $card->only(['teacher_comment', 'administration_comment']);
            $card->update(['teacher_comment' => $data['teacher_comment'], 'administration_comment' => $data['administration_comment']]);
            foreach ($data['subjects'] as $subject) {
                $card->subjects()->whereKey($subject['id'])->update(['appreciation' => $subject['appreciation']]);
            }
            AuditLog::create(['user_id' => auth()->id(), 'event' => 'report_card.comments.updated', 'related_type' => ReportCard::class, 'related_id' => $card->id, 'description' => 'Appréciations du bulletin mises à jour', 'old_values' => $old, 'new_values' => $card->only(['teacher_comment', 'administration_comment']), 'occurred_at' => now()]);
        });

        return back()->with('success', 'Appréciations enregistrées.');
    }

    private function assertTenant(ReportCard $reportCard): void
    {
        abort_unless((int) $reportCard->tenant_id === (int) app(\App\Tenancy\TenantContext::class)->id(), 404);
    }
}
