<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class CompanySetting extends Model
{
    use HasFiles;

    protected $fillable = [
        'trading_name',
        'legal_name',
        'registration_number',
        'tax_number',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'country',
        'phone',
        'secondary_phone',
        'email',
        'website',
        'primary_color',
        'website_disabled',
        'booking_disabled',
        'teacher_login_disabled',
        'tax_enabled',
        'tax_rate',
        'online_advance_percentage',
    ];

    protected $casts = [
        'website_disabled' => 'boolean',
        'booking_disabled' => 'boolean',
        'teacher_login_disabled' => 'boolean',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'decimal:2',
        'online_advance_percentage' => 'decimal:2',
    ];

    protected $appends = ['logo_url', 'favicon_url'];

    public static function defaults(): array
    {
        return [
            'trading_name' => 'Easy École',
            'primary_color' => '#f97316',
            'teacher_login_disabled' => false,
            'tax_enabled' => false,
            'tax_rate' => 7,
            'online_advance_percentage' => 0,
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return new static(static::defaults());
        }

        return static::query()->first() ?? new static(static::defaults());
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->exists || ! Schema::hasTable('files')) {
            return null;
        }

        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'logo')
            : $this->files()->where('collection', 'logo')->first();

        return $file?->path ? Storage::url(str_replace('storage/', '', $file->path)) : null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        if (! $this->exists || ! Schema::hasTable('files')) {
            return null;
        }

        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'favicon')
            : $this->files()->where('collection', 'favicon')->first();

        return $file?->path ? Storage::url(str_replace('storage/', '', $file->path)) : null;
    }
}
