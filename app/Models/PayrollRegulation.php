<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollRegulation extends Model { protected $fillable=['country','name','version','valid_from','valid_until','active','rules','source_references']; protected $casts=['valid_from'=>'date:Y-m-d','valid_until'=>'date:Y-m-d','active'=>'boolean','rules'=>'array','source_references'=>'array']; }
