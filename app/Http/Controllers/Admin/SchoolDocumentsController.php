<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolDocumentType;
use App\Enums\StudentAcademicEnrollmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CompanySetting;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\SchoolDocumentGenerator;
use App\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolDocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $this->selectedYear($request);
        $groups = SchoolGroup::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->where('is_active', true)
            ->with(['level:id,school_cycle_id,name,code,specialization', 'level.cycle:id,name'])
            ->withCount(['academicEnrollments as students_count' => fn ($query) => $query
                ->where('status', StudentAcademicEnrollmentStatus::ENROLLED->value)
                ->whereHas('student', fn ($student) => $student->where('is_active', true))])
            ->orderBy('school_level_id')->orderBy('name')->get();

        return Inertia::render('Admin/SchoolDocuments/Index', [
            'academicYear' => $year,
            'cycles' => SchoolCycle::with(['levels' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'groups' => $groups,
            'teachers' => User::query()->where('role', UserRole::TEACHER)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'documentTypes' => collect(SchoolDocumentType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function download(Request $request, SchoolDocumentGenerator $generator): BinaryFileResponse
    {
        // A batch contains one Dompdf render per student. Give the request enough
        // headroom and explicitly collect Dompdf's cyclic object graph after every
        // document so larger groups do not exhaust the default 128 MB limit.
        $this->ensureGenerationResources();

        $data = $request->validate([
            'document_type' => ['required', Rule::enum(SchoolDocumentType::class)],
            'language' => ['required', Rule::in(['fr', 'ar'])],
            'issue_date' => ['required', 'date'],
            'group_ids' => [Rule::requiredIf($request->input('document_type') !== SchoolDocumentType::TEACHER_TIMETABLE->value), 'array', 'min:1'],
            'group_ids.*' => ['integer', 'distinct', TenantRule::exists('school_groups')],
            'teacher_ids' => [Rule::requiredIf($request->input('document_type') === SchoolDocumentType::TEACHER_TIMETABLE->value), 'array', 'min:1'],
            'teacher_ids.*' => ['integer', 'distinct', TenantRule::exists('users')],
        ]);
        $type = SchoolDocumentType::from($data['document_type']);
        $year = $this->selectedYear($request) ?? throw ValidationException::withMessages([
            $type === SchoolDocumentType::TEACHER_TIMETABLE ? 'teacher_ids' : 'group_ids' => 'Sélectionnez une année scolaire.',
        ]);
        $school = CompanySetting::current();
        if ($type === SchoolDocumentType::TEACHER_TIMETABLE) {
            $teachers = User::query()
                ->where('role', UserRole::TEACHER)
                ->where('is_active', true)
                ->whereIn('id', $data['teacher_ids'] ?? [])
                ->get();
            if ($teachers->count() !== count($data['teacher_ids'] ?? [])) {
                throw ValidationException::withMessages(['teacher_ids' => 'Un enseignant sélectionné est invalide.']);
            }

            return $this->downloadTeacherTimetables($teachers, $year, $school, $generator);
        }

        $groups = SchoolGroup::where('academic_year_id', $year->id)->whereIn('id', $data['group_ids'])->get();
        if ($groups->count() !== count($data['group_ids'])) {
            throw ValidationException::withMessages(['group_ids' => "Un groupe ne fait pas partie de l'année scolaire sélectionnée."]);
        }

        if ($type === SchoolDocumentType::GROUP_TIMETABLE) {
            return $this->downloadGroupTimetables($groups->load(['academicYear', 'level.cycle', 'classroom', 'principalTeacher']), $year, $school, $generator);
        }

        $enrollments = StudentAcademicEnrollment::with(['student', 'academicYear', 'level', 'stream', 'group'])
            ->where('academic_year_id', $year->id)
            ->where('status', StudentAcademicEnrollmentStatus::ENROLLED->value)
            ->whereIn('school_group_id', $groups->pluck('id'))
            ->whereHas('student', fn ($query) => $query->where('is_active', true))
            ->get()->sortBy(fn ($enrollment) => $enrollment->group?->name.' '.$enrollment->student?->last_name.' '.$enrollment->student?->first_name);
        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages(['group_ids' => 'Aucun élève inscrit et actif dans les groupes sélectionnés.']);
        }
        $temporaryFile = $this->temporaryArchive();
        try {
            $zip = new PharData($temporaryFile);
            foreach ($enrollments as $enrollment) {
                $group = $this->safeFilename($enrollment->group?->name ?: 'sans-groupe');
                $student = $this->safeFilename($enrollment->student->full_name);
                $filename = $group.'/'.$student.'-'.$enrollment->student_id.'.pdf';
                $pdf = $generator->pdf($type, $enrollment, $school, $data['language'], $data['issue_date']);
                $zip->addFromString($filename, $pdf);
                unset($pdf);
                gc_collect_cycles();
            }
            unset($zip);
        } catch (\Throwable $exception) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }

            throw $exception;
        }

        $language = $data['language'] === 'ar' ? 'arabe' : 'francais';
        $archiveName = 'certificats-scolarite-'.$this->safeFilename($year->name).'-'.$language.'.zip';

        return response()->download($temporaryFile, $archiveName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    private function downloadTeacherTimetables($teachers, AcademicYear $year, CompanySetting $school, SchoolDocumentGenerator $generator): BinaryFileResponse
    {
        $temporaryFile = $this->temporaryArchive();

        try {
            $zip = new PharData($temporaryFile);
            foreach ($teachers as $teacher) {
                $filename = 'Emploi-du-temps-'.$this->safeFilename($teacher->name).'-'.$teacher->id.'-'.$this->safeFilename($year->name).'.pdf';
                $pdf = $generator->teacherTimetablePdf($teacher, $year, $school);
                $zip->addFromString($filename, $pdf);
                unset($pdf);
                gc_collect_cycles();
            }
            unset($zip);
        } catch (\Throwable $exception) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
            throw $exception;
        }

        $archiveName = 'emplois-du-temps-enseignants-'.$this->safeFilename($year->name).'.zip';

        return response()->download($temporaryFile, $archiveName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    private function downloadGroupTimetables($groups, AcademicYear $year, CompanySetting $school, SchoolDocumentGenerator $generator): BinaryFileResponse
    {
        $temporaryFile = $this->temporaryArchive();

        try {
            $zip = new PharData($temporaryFile);
            foreach ($groups as $group) {
                $filename = 'Emploi-du-temps-'.$this->safeFilename($group->name).'-'.$this->safeFilename($year->name).'.pdf';
                $pdf = $generator->groupTimetablePdf($group, $school);
                $zip->addFromString($filename, $pdf);
                unset($pdf);
                gc_collect_cycles();
            }
            unset($zip);
        } catch (\Throwable $exception) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
            throw $exception;
        }

        $archiveName = 'emplois-du-temps-'.$this->safeFilename($year->name).'.zip';

        return response()->download($temporaryFile, $archiveName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    private function temporaryArchive(): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'school-documents-');
        abort_if($temporaryFile === false, 500, "Impossible de préparer l'archive.");
        unlink($temporaryFile);

        return $temporaryFile.'.zip';
    }

    private function selectedYear(Request $request): ?AcademicYear
    {
        return AcademicYear::find($request->session()->get('academic_year_id'))
            ?? AcademicYear::where('status', 'active')->first()
            ?? AcademicYear::latest('start_date')->first();
    }

    private function ensureGenerationResources(): void
    {
        $memoryLimit = ini_get('memory_limit');
        $bytes = $memoryLimit === false || $memoryLimit === '-1'
            ? -1
            : (int) $memoryLimit * match (strtolower(substr($memoryLimit, -1))) {
                'g' => 1024 ** 3,
                'm' => 1024 ** 2,
                'k' => 1024,
                default => 1,
            };

        if ($bytes !== -1 && $bytes < 512 * 1024 ** 2) {
            ini_set('memory_limit', '512M');
        }

        set_time_limit(300);
        gc_enable();
    }

    private function safeFilename(string $value): string
    {
        $value = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/\:\*\?\"\<\>\|]+/u', '-', trim($value)) ?: 'document';

        return mb_substr($value, 0, 100);
    }
}
