<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class PortalNotification extends Model {protected $fillable=['recipient_id','type','title','message','related_type','related_id','data','channels','delivery_state','read_at','occurred_at'];protected $casts=['data'=>'array','channels'=>'array','delivery_state'=>'array','read_at'=>'datetime','occurred_at'=>'datetime'];public function recipient():BelongsTo{return $this->belongsTo(User::class,'recipient_id');}public function related():MorphTo{return $this->morphTo();}}
