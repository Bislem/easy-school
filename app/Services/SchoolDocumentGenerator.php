<?php

namespace App\Services;

use App\Enums\SchoolDocumentType;
use App\Models\CompanySetting;
use App\Models\StudentAcademicEnrollment;
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
        };

        return Pdf::loadView($view, [
            'enrollment' => $enrollment,
            'school' => $school,
            'language' => $language,
            'issueDate' => $issueDate,
            'schoolLogo' => $this->localImage($school->logo_url),
        ])->setPaper('a4', 'portrait')->output();
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
