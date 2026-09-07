<?php
namespace App\Services;
use App\Contracts\DeclarationExporter;
use App\Models\PayrollDeclaration;
use RuntimeException;
class CnasDasExporter implements DeclarationExporter
{
    public function supported():bool{return false;}
    public function export(PayrollDeclaration $declaration):array{throw new RuntimeException("Export DAS désactivé : le schéma fixe officiel complet et les règles de nommage ne sont pas disponibles dans la documentation du projet.");}
}
