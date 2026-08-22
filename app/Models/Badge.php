<?php
namespace App\Models;
use App\Enums\BadgeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class Badge extends Model
{
    protected $fillable=['badgeable_type','badgeable_id','badge_template_id','replaces_badge_id','card_number','verification_token','barcode_value','issue_date','expiration_date','status','first_name','last_name','person_type','role_label','formation_label','group_label','photo_url_snapshot','issued_by','status_changed_by','status_changed_at','status_reason','metadata'];
    protected $hidden=['verification_token'];
    protected $casts=['issue_date'=>'date:Y-m-d','expiration_date'=>'date:Y-m-d','status'=>BadgeStatus::class,'status_changed_at'=>'datetime','metadata'=>'array'];
    protected $appends=['verification_url','qr_url','barcode_url','display_status'];
    public function badgeable(): MorphTo { return $this->morphTo(); }
    public function template(): BelongsTo { return $this->belongsTo(BadgeTemplate::class,'badge_template_id'); }
    public function replacedBadge(): BelongsTo { return $this->belongsTo(self::class,'replaces_badge_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class,'issued_by'); }
    public function getVerificationUrlAttribute(): string { return route('badges.verify',$this->verification_token); }
    public function getQrUrlAttribute(): string { return route('badges.qr',$this->verification_token); }
    public function getBarcodeUrlAttribute(): ?string { return $this->barcode_value ? route('badges.barcode',$this->verification_token) : null; }
    public function getDisplayStatusAttribute(): string { return $this->expiration_date?->isPast() && $this->status===BadgeStatus::ACTIVE ? BadgeStatus::EXPIRED->value : $this->status->value; }
}
