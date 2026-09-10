<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantStoredFile;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenantStorageService
{
    public const STUDENT_DOCUMENTS = 'student_documents';
    public const HR_DOCUMENTS = 'employee_hr_documents';
    public const PROFILE_IMAGES = 'profile_images';
    public const CERTIFICATES = 'certificates_report_cards';
    public const ATTACHMENTS = 'attachments';
    public const OTHER = 'other';

    public function store(UploadedFile $file, string $directory, string $disk, string $category, ?string $module = null, ?Model $related = null, ?Tenant $tenant = null): string
    {
        $tenant ??= $this->tenant();
        $size = max(0, (int) $file->getSize());
        $path = null;
        try {
            return DB::transaction(function () use ($file, $directory, $disk, $category, $module, $related, $tenant, $size, &$path): string {
                $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                $this->assertWithinLimit($locked, $size);
                $path = $file->store("tenants/{$tenant->id}/".trim($directory, '/'), $disk);
                $this->record($locked, $disk, $path, $file->getClientOriginalName(), $size, $file->getMimeType(), $category, $module, $related);

                return $path;
            });
        } catch (Throwable $exception) {
            if ($path) Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function registerExisting(string $disk, string $storageKey, string $originalName, int $size, ?string $mime, string $category, ?string $module = null, ?Model $related = null, ?Tenant $tenant = null): TenantStoredFile
    {
        $tenant ??= $this->tenant();
        return DB::transaction(function () use ($disk, $storageKey, $originalName, $size, $mime, $category, $module, $related, $tenant) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $this->assertWithinLimit($locked, $size);
            return $this->record($locked, $disk, $storageKey, $originalName, $size, $mime, $category, $module, $related);
        });
    }

    public function delete(string $storageKey, string $disk = 'public', ?Tenant $tenant = null): bool
    {
        $tenant ??= $this->tenant();
        $deleted = Storage::disk($disk)->delete($storageKey);
        $this->forget($storageKey, $disk, $tenant);
        return $deleted;
    }

    public function forget(string $storageKey, string $disk, ?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenant();
        DB::transaction(function () use ($storageKey, $disk, $tenant): void {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $metadata = TenantStoredFile::withoutGlobalScopes()->where('tenant_id', $locked->id)->where('disk', $disk)->where('storage_key', $storageKey)->first();
            if (! $metadata) return;
            $metadata->delete();
            $locked->update(['storage_used_bytes' => max(0, $locked->storage_used_bytes - $metadata->size_bytes)]);
        });
    }

    public function summary(Tenant $tenant): array
    {
        $used = (int) $tenant->storage_used_bytes;
        $limit = $tenant->storage_limit_bytes === null ? null : (int) $tenant->storage_limit_bytes;
        return ['used_bytes' => $used, 'limit_bytes' => $limit, 'percentage' => $limit ? min(100, round($used / $limit * 100, 1)) : 0,
            'breakdown' => TenantStoredFile::withoutGlobalScopes()->where('tenant_id', $tenant->id)->selectRaw('category, SUM(size_bytes) as bytes, COUNT(*) as files')->groupBy('category')->get()->map(fn ($row) => ['category' => $row->category, 'bytes' => (int) $row->bytes, 'files' => (int) $row->files])->values()];
    }

    public function repair(?Tenant $tenant = null): int
    {
        $query = $tenant ? Tenant::whereKey($tenant->id) : Tenant::query();
        $count = 0;
        $query->each(function (Tenant $item) use (&$count): void {
            $item->update(['storage_used_bytes' => TenantStoredFile::withoutGlobalScopes()->where('tenant_id', $item->id)->sum('size_bytes')]);
            $count++;
        });
        return $count;
    }

    public function assertWithinLimit(Tenant $tenant, int $additionalBytes): void
    {
        if ($tenant->storage_limit_bytes !== null && $tenant->storage_used_bytes + $additionalBytes > $tenant->storage_limit_bytes) {
            throw ValidationException::withMessages(['file' => 'Espace de stockage insuffisant. Supprimez des fichiers ou mettez à niveau votre abonnement.']);
        }
    }

    private function record(Tenant $tenant, string $disk, string $key, string $name, int $size, ?string $mime, string $category, ?string $module, ?Model $related): TenantStoredFile
    {
        $metadata = TenantStoredFile::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'disk' => $disk, 'storage_key' => $key, 'original_filename' => $name, 'size_bytes' => $size, 'mime_type' => $mime, 'category' => $category, 'module' => $module, 'related_entity_type' => $related?->getMorphClass(), 'related_entity_id' => $related?->getKey()]);
        $tenant->update(['storage_used_bytes' => $tenant->storage_used_bytes + $size]);
        return $metadata;
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(TenantContext::class)->id());
    }
}
