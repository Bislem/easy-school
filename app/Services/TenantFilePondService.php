<?php

namespace App\Services;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use MohamedGaldi\ViltFilepond\Models\File;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class TenantFilePondService extends FilePondService
{
    public function __construct(private TenantStorageService $tenantStorage) {}

    public function moveTempFileToModel($model, string $folder, string $collection = 'default', int $order = 0): ?File
    {
        $temp = TempFile::where('folder', $folder)->first();
        if (! $temp) return null;
        $tenant = Tenant::findOrFail(app(TenantContext::class)->id());
        $this->tenantStorage->assertWithinLimit($tenant, (int) $temp->size);
        $file = parent::moveTempFileToModel($model, $folder, $collection, $order);
        if ($file) {
            $category = match (true) {
                $model instanceof \App\Models\Student => TenantStorageService::STUDENT_DOCUMENTS,
                $model instanceof \App\Models\Staff => TenantStorageService::HR_DOCUMENTS,
                in_array($collection, ['cover', 'logo', 'favicon'], true) => TenantStorageService::PROFILE_IMAGES,
                default => TenantStorageService::ATTACHMENTS,
            };
            $this->tenantStorage->registerExisting(
                config('vilt-filepond.storage_disk'),
                $file->getCleanPath(),
                $file->original_name,
                (int) $file->size,
                $file->mime_type,
                $category,
                $collection,
                $model,
                $tenant,
            );
        }
        return $file;
    }
}
