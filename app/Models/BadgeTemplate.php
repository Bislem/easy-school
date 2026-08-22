<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class BadgeTemplate extends Model
{
    protected $fillable=['name','slug','primary_color','secondary_color','text_color','show_address','show_contact','show_barcode','is_default','settings'];
    protected $casts=['show_address'=>'boolean','show_contact'=>'boolean','show_barcode'=>'boolean','is_default'=>'boolean','settings'=>'array'];
    public function badges(): HasMany { return $this->hasMany(Badge::class); }
}
