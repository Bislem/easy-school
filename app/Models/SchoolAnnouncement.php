<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;
class SchoolAnnouncement extends Model
{
    use HasFiles;
    protected $fillable = ['created_by','title','message','delivery','target','status','cycle_ids','poster_path','recipient_count','published_at','notified_at'];
    protected $appends = ['poster_url'];
    protected function casts(): array { return ['cycle_ids'=>'array','published_at'=>'datetime','notified_at'=>'datetime']; }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function notifications(): MorphMany { return $this->morphMany(PortalNotification::class, 'related'); }
    public function getPosterUrlAttribute(): ?string { return ($this->relationLoaded('files') ? $this->files->firstWhere('collection', 'poster') : $this->files()->where('collection', 'poster')->first())?->url ?: ($this->poster_path ? asset('storage/'.$this->poster_path) : null); }
}
