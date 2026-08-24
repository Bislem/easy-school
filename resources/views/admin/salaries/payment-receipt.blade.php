<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page{margin:32px}body{font-family:DejaVu Sans,sans-serif;color:#17202a;font-size:11px;line-height:1.5}.header{border-bottom:3px solid #159a8c;padding-bottom:14px}h1{margin:0;color:#123f5a;font-size:24px}.muted{color:#64748b}.box{margin-top:18px;border:1px solid #dce4e8;border-radius:6px;padding:14px}.identity{background:#f1f7f6;border-left:4px solid #159a8c}.identity strong{font-size:15px}table{width:100%;border-collapse:collapse;margin-top:16px}td{padding:8px;border-bottom:1px solid #e5e7eb}.right{text-align:right}.total td{background:#123f5a;color:#fff;font-size:15px;font-weight:bold}.footer{margin-top:32px;border-top:1px solid #dce4e8;padding-top:10px;color:#64748b;font-size:9px}.signatures{margin-top:42px;width:100%}.signatures td{width:50%;border:0;text-align:center;padding-top:35px}
    </style>
</head>
<body>
@php($statement = $payment->statement)
@php($hours = (float) ($statement->calculation_details['attendance_worked_hours'] ?? 0))
<div class="header">
    <h1>Reçu de paiement de salaire</h1>
    <div class="muted">{{ $school?->trading_name ?? config('app.name') }} · Émis le {{ now()->format('d/m/Y à H:i') }}</div>
</div>
<div class="box identity">
    <strong>{{ $payment->staff->name }}</strong><br>
    Matricule {{ $payment->staff->employee_code }} · {{ $payment->staff->employeeType?->name ?? 'Personnel' }}<br>
    Période salariale : {{ $statement->period_start->format('d/m/Y') }} au {{ $statement->period_end->format('d/m/Y') }}
</div>
<table>
    <tr><td>Mode de calcul</td><td class="right">{{ match($statement->salary_type->value){'monthly'=>'Mensuel fixe','hourly'=>'Horaire','per_session'=>'Par séance','daily'=>'Journalier','custom'=>'Manuel'} }}</td></tr>
    <tr><td>Configuration</td><td class="right">{{ $statement->configuration?->name ?? 'Configuration archivée' }}</td></tr>
    <tr><td>Heures travaillées tracées</td><td class="right"><strong>{{ number_format($hours, 2, ',', ' ') }} h</strong></td></tr>
    <tr><td>Base × unités</td><td class="right">{{ number_format((float)$statement->base_rate,2,',',' ') }} {{ $currency }} × {{ number_format((float)$statement->units,2,',',' ') }}</td></tr>
    <tr><td>Salaire brut</td><td class="right">{{ number_format((float)$statement->gross_salary,2,',',' ') }} {{ $currency }}</td></tr>
    <tr><td>Primes / exceptionnel / remboursements</td><td class="right">+ {{ number_format((float)$statement->bonuses+(float)$statement->exceptional_payments+(float)$statement->reimbursements,2,',',' ') }} {{ $currency }}</td></tr>
    <tr><td>Retenues / avances</td><td class="right">- {{ number_format((float)$statement->deductions+(float)$statement->advances,2,',',' ') }} {{ $currency }}</td></tr>
    <tr><td>Net du bulletin</td><td class="right">{{ number_format((float)$statement->net_salary,2,',',' ') }} {{ $currency }}</td></tr>
    <tr><td>Date et méthode du paiement</td><td class="right">{{ $payment->paid_at->format('d/m/Y') }} · {{ ucfirst(str_replace('_',' ',$payment->payment_method)) }}</td></tr>
    <tr class="total"><td>Montant reçu</td><td class="right">{{ number_format((float)$payment->amount,2,',',' ') }} {{ $currency }}</td></tr>
</table>
@if($payment->notes)<div class="box"><strong>Note</strong><br>{{ $payment->notes }}</div>@endif
<table class="signatures"><tr><td>Signature de l’employé</td><td>Cachet et signature de l’établissement</td></tr></table>
<div class="footer">Ce reçu correspond à un paiement immuable enregistré dans l’historique salarial. Les heures indiquées proviennent des pointages attachés au bulletin.</div>
</body>
</html>
