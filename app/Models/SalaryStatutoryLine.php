<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalaryStatutoryLine extends Model { protected $fillable=['salary_statement_id','code','label','base','rate','amount','affects_net','employer_charge','details']; protected $casts=['base'=>'decimal:2','rate'=>'decimal:4','amount'=>'decimal:2','affects_net'=>'boolean','employer_charge'=>'boolean','details'=>'array']; public function statement():BelongsTo{return $this->belongsTo(SalaryStatement::class,'salary_statement_id');} }
