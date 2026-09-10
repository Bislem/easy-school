<?php

namespace App\Services;

use App\Enums\SchoolDocumentType;
use App\Models\CompanySetting;
use App\Models\SchoolGroup;
use App\Models\StudentAcademicEnrollment;
use App\Models\TimetableSetting;
use App\Models\TimetableSession;
use App\Models\User;
use App\Models\AcademicYear;
use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;

class SchoolDocumentGenerator
{
    public function pdf(
        SchoolDocumentType $type,
        StudentAcademicEnrollment $enrollment,
        CompanySetting $school,
        string $language,
        string $issueDate,
    ): string {
        $view = match ($type) {
            SchoolDocumentType::SCHOOL_CERTIFICATE => 'admin.school-documents.school-certificate',
            SchoolDocumentType::GROUP_TIMETABLE, SchoolDocumentType::TEACHER_TIMETABLE => throw new \InvalidArgumentException('A timetable requires its target entity.'),
        };

        $arabic = $language === 'ar' ? new Arabic : null;

        return Pdf::loadView($view, [
            'enrollment' => $enrollment,
            'school' => $school,
            'language' => $language,
            'issueDate' => $issueDate,
            'schoolLogo' => $this->localImage($school->logo_url),
            // Dompdf does not implement Arabic glyph joining or bidi. Ar-PHP
            // converts logical Arabic into the visual glyph order it can render.
            'ar' => fn (?string $text): string => $text && $arabic
                ? $arabic->utf8Glyphs($text, 200, false)
                : (string) $text,
        ])->setPaper('a4', 'portrait')->output();
    }

    public function groupTimetablePdf(SchoolGroup $group, CompanySetting $school): string
    {
        $schedule = $this->scheduleData();
        $sessions = $group->timetableSessions()
            ->with(['subject', 'teacher', 'room'])
            ->whereNull('effective_date')
            ->where('recurrence', 'weekly')
            ->where('status', '!=', 'cancelled')
            ->orderBy('day')->orderBy('start_time')->get();

        return Pdf::loadView('admin.school-documents.group-timetable', [
            'group' => $group,
            'school' => $school,
            'schoolLogo' => $this->localImage($school->logo_url),
            'days' => $schedule['days'],
            'slots' => $schedule['slots'],
            'sessions' => $sessions,
            'breaks' => $schedule['breaks'],
        ])->setPaper('a4', 'landscape')->output();
    }

    public function teacherTimetablePdf(User $teacher, AcademicYear $year, CompanySetting $school): string
    {
        $schedule = $this->scheduleData();
        $sessions = TimetableSession::query()
            ->with(['group.level.cycle', 'subject', 'room'])
            ->where('academic_year_id', $year->id)
            ->where('teacher_id', $teacher->id)
            ->whereNull('effective_date')
            ->where('recurrence', 'weekly')
            ->where('status', '!=', 'cancelled')
            ->orderBy('day')->orderBy('start_time')->get();

        return Pdf::loadView('admin.school-documents.group-timetable', [
            'group' => null,
            'teacher' => $teacher,
            'year' => $year,
            'school' => $school,
            'schoolLogo' => $this->localImage($school->logo_url),
            'days' => $schedule['days'],
            'slots' => $schedule['slots'],
            'sessions' => $sessions,
            'breaks' => $schedule['breaks'],
        ])->setPaper('a4', 'landscape')->output();
    }

    private function scheduleData(): array
    {
        $settings = TimetableSetting::first() ?? TimetableSetting::make(TimetableSetting::defaults());

        return [
            'days' => collect($settings->working_days ?: TimetableSetting::defaults()['working_days'])
                ->sortBy(fn (int $day) => array_search($day, [7, 1, 2, 3, 4, 5, 6], true))->values(),
            'slots' => $settings->time_slots ?: $this->buildSlots(
                substr($settings->day_starts_at ?: '08:00', 0, 5),
                substr($settings->day_ends_at ?: '17:00', 0, 5),
                $settings->default_session_duration ?: 60,
                $settings->breaks ?: [],
            ),
            'breaks' => $settings->breaks ?: [],
        ];
    }

    private function buildSlots(string $start, string $end, int $duration, array $breaks): array
    {
        $toMinutes = fn (string $time): int => ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
        $toTime = fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        $cursor = $toMinutes($start);
        $dayEnd = $toMinutes($end);
        usort($breaks, fn (array $a, array $b) => strcmp($a['start_time'] ?? $a['start'] ?? '', $b['start_time'] ?? $b['start'] ?? ''));
        $slots = [];

        while ($cursor < $dayEnd) {
            $activeBreak = collect($breaks)->first(fn (array $break) => $toMinutes($break['start_time'] ?? $break['start']) === $cursor);
            $slotEnd = $activeBreak
                ? min($dayEnd, $toMinutes($activeBreak['end_time'] ?? $activeBreak['end']))
                : min($dayEnd, $cursor + $duration);
            if (! $activeBreak) {
                $nextBreak = collect($breaks)->first(fn (array $break) => $toMinutes($break['start_time'] ?? $break['start']) > $cursor && $toMinutes($break['start_time'] ?? $break['start']) < $slotEnd);
                if ($nextBreak) {
                    $slotEnd = $toMinutes($nextBreak['start_time'] ?? $nextBreak['start']);
                }
            }
            if ($slotEnd <= $cursor) {
                break;
            }
            $slots[] = ['start_time' => $toTime($cursor), 'end_time' => $toTime($slotEnd)];
            $cursor = $slotEnd;
        }

        return $slots;
    }

    private function localImage(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! str_starts_with((string) $path, '/storage/')) {
            return null;
        }

        $file = public_path(ltrim($path, '/'));
        if (! is_file($file)) {
            return null;
        }

        return 'data:'.(mime_content_type($file) ?: 'image/png').';base64,'.base64_encode(file_get_contents($file));
    }
}
