<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:12px;line-height:1.55} .header{border-bottom:3px solid #f97316;padding-bottom:14px;margin-bottom:28px}.school{font-size:21px;font-weight:bold}.muted{color:#64748b}.title{text-align:center;font-size:20px;text-transform:uppercase;letter-spacing:.7px;margin:28px 0}.box{border:1px solid #cbd5e1;border-radius:7px;padding:14px;margin:14px 0}.grid{width:100%;border-collapse:collapse}.grid td{width:50%;padding:6px 8px;border-bottom:1px solid #e2e8f0}.statement{font-size:13px;text-align:justify;margin:24px 6px}.signatures{width:100%;margin-top:55px}.signatures td{width:50%;text-align:center;vertical-align:top}.line{margin:50px auto 0;border-top:1px solid #334155;width:75%}.footer{position:fixed;bottom:0;width:100%;text-align:center;font-size:9px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:6px}
</style></head><body>
@php
 $titles=['request'=>'Demande de congé annuel','authorization'=>'Décision d’autorisation de congé annuel','return'=>'Attestation de reprise de travail'];
 $employee=$leave->staff; $schoolName=$school->trading_name ?: ($school->legal_name ?: 'Établissement');
@endphp
<div class="header"><div class="school">{{ $schoolName }}</div><div class="muted">{{ collect([$school->address_line_1,$school->city,$school->phone,$school->email])->filter()->join(' · ') }}</div></div>
<div class="title">{{ $titles[$type] }}</div>
<div class="box"><table class="grid">
 <tr><td><b>Employé(e) :</b> {{ $employee->name }}</td><td><b>Matricule :</b> {{ $employee->employee_code ?: '—' }}</td></tr>
 <tr><td><b>Fonction :</b> {{ $employee->employeeType?->name ?: '—' }}</td><td><b>N° sécurité sociale :</b> {{ $employee->social_security_number ?: '—' }}</td></tr>
 <tr><td><b>Né(e) le :</b> {{ $employee->birth_date?->format('d/m/Y') ?: '—' }}{{ $employee->place_of_birth ? ' à '.$employee->place_of_birth : '' }}</td><td><b>Nationalité :</b> {{ $employee->nationality ?: '—' }}</td></tr>
 <tr><td><b>Document d’identité :</b> {{ collect([$employee->identification_type,$employee->identification_number])->filter()->join(' n° ') ?: '—' }}</td><td><b>Téléphone :</b> {{ $employee->phone ?: '—' }}</td></tr>
 <tr><td colspan="2"><b>Adresse :</b> {{ $employee->address ?: '—' }}</td></tr>
 <tr><td><b>Période :</b> {{ $leave->starts_at->format('d/m/Y') }} au {{ $leave->ends_at->format('d/m/Y') }}</td><td><b>Durée imputée :</b> {{ (float)$leave->days }} jour(s)</td></tr>
 <tr><td><b>Format :</b> {{ $leave->mode==='full_month'?'Mois complet':'Période en jours' }}</td><td><b>Référence :</b> CA-{{ str_pad($leave->id,6,'0',STR_PAD_LEFT) }}</td></tr>
</table></div>
@if($type==='request')
<p class="statement">Je soussigné(e), <b>{{ $employee->name }}</b>, sollicite un congé annuel pour la période indiquée ci-dessus, représentant {{ (float)$leave->days }} jour(s). @if($leave->reason) Motif déclaré : {{ $leave->reason }}. @endif</p>
@elseif($type==='authorization')
<p class="statement">La direction autorise <b>{{ $employee->name }}</b> à bénéficier de son congé annuel durant la période indiquée ci-dessus. L’intéressé(e) devra reprendre son poste à l’issue du congé, sauf décision contraire dûment enregistrée.</p>
@else
<p class="statement">Nous attestons que <b>{{ $employee->name }}</b>, après son congé annuel, a effectivement repris son poste le <b>{{ $leave->actual_return_date?->format('d/m/Y') }}</b>.</p>
@endif
<table class="signatures"><tr><td>L’employé(e)<div class="line">Signature</div></td><td>La direction / Responsable RH<div class="line">Cachet et signature</div></td></tr></table>
<div class="footer">Document généré le {{ now()->format('d/m/Y à H:i') }} · {{ $schoolName }} · CA-{{ str_pad($leave->id,6,'0',STR_PAD_LEFT) }}</div>
</body></html>
