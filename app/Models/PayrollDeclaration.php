<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollDeclaration extends Model { protected $fillable=['declaration_type','period','payroll_regulation_id','employee_count','declared_amount','status','validation_result','generated_files','generated_at','generated_by','declared_at']; protected $casts=['declared_amount'=>'decimal:2','validation_result'=>'array','generated_files'=>'array','generated_at'=>'datetime','declared_at'=>'datetime']; }
