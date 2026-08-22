<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;use Illuminate\Database\Eloquent\Relations\MorphTo;
class AuditLog extends Model {protected $fillable=['user_id','event','related_type','related_id','description','old_values','new_values','ip_address','user_agent','occurred_at'];protected $casts=['old_values'=>'array','new_values'=>'array','occurred_at'=>'datetime'];public function user():BelongsTo{return $this->belongsTo(User::class);}public function related():MorphTo{return $this->morphTo();}}
