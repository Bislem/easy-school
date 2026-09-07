<?php
namespace App\Contracts;
use App\Models\PayrollDeclaration;
interface DeclarationExporter { public function supported():bool; public function export(PayrollDeclaration $declaration):array; }
