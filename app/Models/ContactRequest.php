<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRequest extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'organization', 'subject', 'message', 'status', 'read_at', 'resolved_at', 'handled_by', 'internal_note', 'ip_address'];

    protected function casts(): array { return ['read_at' => 'datetime', 'resolved_at' => 'datetime']; }

    public function handler(): BelongsTo { return $this->belongsTo(User::class, 'handled_by')->withoutGlobalScopes(); }
}
