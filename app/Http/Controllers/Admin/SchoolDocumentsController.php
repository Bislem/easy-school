<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolDocumentType;
use App\Enums\StudentAcademicEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CompanySetting;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\StudentAcademicEnrollment;
use App\Services\SchoolDocumentGenerator;
use App\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Phar;
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
            'documentTypes' => collect(SchoolDocumentType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function download(Request $request, SchoolDocumentGenerator $generator): BinaryFileResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::enum(SchoolDocumentType::class)],
            'language' => ['required', Rule::in(['fr', 'ar'])],
            'issue_date' => ['required', 'date'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'distinct', TenantRule::exists('school_groups')],
        ]);
        $year = $this->selectedYear($request) ?? throw ValidationException::withMessages([
            'group_ids' => 'Sélectionnez une année scolaire.',
        ]);
        $groups = SchoolGroup::where('academic_year_id', $year->id)->whereIn('id', $data['group_ids'])->get();
        if ($groups->count() !== count($data['group_ids'])) {
            throw ValidationException::withMessages(['group_ids' => "Un groupe ne fait pas partie de l'année scolaire sélectionnée."]);
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
        $temporaryFile = tempnam(sys_get_temp_dir(), 'school-documents-');
        abort_if($temporaryFile === false, 500, "Impossible de préparer l'archive.");
        unlink($temporaryFile);
        $temporaryFile .= '.zip';
        $type = SchoolDocumentType::from($data['document_type']);
        $school = CompanySetting::current();

        try {
            $zip = new PharData($temporaryFile);
            foreach ($enrollments as $enrollment) {
                $group = $this->safeFilename($enrollment->group?->name ?: 'sans-groupe');
                $student = $this->safeFilename($enrollment->student->full_name);
                $filename = $group.'/'.$student.'-'.$enrollment->student_id.'.pdf';
                $zip->addFromString($filename, $generator->pdf($type, $enrollment, $school, $data['language'], $data['issue_date']));
            }
            if (extension_loaded('zlib')) {
                $zip->compressFiles(Phar::GZ);
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

    private function selectedYear(Request $request): ?AcademicYear
    {
        return AcademicYear::find($request->session()->get('academic_year_id'))
            ?? AcademicYear::where('status', 'active')->first()
            ?? AcademicYear::latest('start_date')->first();
    }

    private function safeFilename(string $value): string
    {
        $value = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/\:\*\?\"\<\>\|]+/u', '-', trim($value)) ?: 'document';

        return mb_substr($value, 0, 100);
    }
}
