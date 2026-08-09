<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat {{ $reservation->reservation_number }}</title>
    <style>
        @page { margin: 7mm 8mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #182230; font-family: DejaVu Sans, sans-serif; font-size: 8.2px; line-height: 1.32; }
        .page { position: relative; width: 100%; height: 282mm; overflow: hidden; }
        .page-break { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .brand-table { border-bottom: 2px solid {{ $agency->primary_color ?: '#f97316' }}; padding-bottom: 5px; margin-bottom: 7px; }
        .brand-table td { vertical-align: middle; }
        .logo { display: block; max-width: 20mm; max-height: 15mm; margin: 0; }
        .brand-details { padding-left: 0; }
        .brand-name { color: {{ $agency->primary_color ?: '#f97316' }}; font-size: 13px; font-weight: bold; letter-spacing: .6px; }
        .legal-name { margin-top: 1px; color: #344054; font-size: 7px; font-weight: bold; }
        .phones { margin-top: 3px; }
        .phone { display: inline-block; margin-right: 3px; padding: 2px 5px; border-radius: 3px; background: {{ $agency->primary_color ?: '#f97316' }}; color: #fff; font-size: 7.5px; font-weight: bold; }
        .eyebrow { color: #667085; font-size: 7.1px; letter-spacing: 1.2px; text-transform: uppercase; }
        .contract-meta { text-align: right; font-size: 7.5px; }
        .contract-number { color: {{ $agency->primary_color ?: '#f97316' }}; font-size: 12px; font-weight: bold; }
        h1 { margin: 7px 0 1px; color: #101828; font-size: 16px; letter-spacing: 2px; text-align: center; }
        .subtitle { margin-bottom: 7px; color: #667085; font-size: 7.2px; letter-spacing: .6px; text-align: center; text-transform: uppercase; }
        .section { margin-bottom: 5px; border: 1px solid #d0d5dd; border-radius: 4px; }
        .section-title { padding: 3px 6px; background: {{ $agency->primary_color ?: '#f97316' }}; color: white; font-size: 7.7px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .section-body { padding: 4px 6px; }
        .field { min-height: 8mm; padding: 2px 3px; border-bottom: 1px solid #eaecf0; }
        .field:last-child { border-bottom: 0; }
        .label { display: block; color: #667085; font-size: 6.6px; font-weight: bold; letter-spacing: .3px; text-transform: uppercase; }
        .value { display: block; margin-top: 1px; color: #101828; font-size: 8.2px; font-weight: bold; }
        .muted { color: #667085; }
        .columns > tbody > tr > td:first-child { padding-right: 3px; }
        .columns > tbody > tr > td:last-child { padding-left: 3px; }
        .data-grid td { width: 50%; padding: 0 2px; }
        .data-grid td:first-child { padding-left: 0; }
        .data-grid td:last-child { padding-right: 0; }
        .money-table td { padding: 2px 3px; border-bottom: 1px solid #eaecf0; }
        .money-table td:last-child { text-align: right; font-weight: bold; }
        .money-total td { color: {{ $agency->primary_color ?: '#f97316' }}; font-size: 9px; font-weight: bold; }
        .fuel { display: inline-block; margin-left: 3px; padding: 1px 4px; border: 1px solid #d0d5dd; border-radius: 8px; }
        .fuel.active { border-color: {{ $agency->primary_color ?: '#f97316' }}; background: {{ $agency->primary_color ?: '#f97316' }}; color: white; }
        .inspection td { width: 50%; padding: 0 3px; }
        .inspection-box { height: 42mm; padding: 2px 3px; border: 1px dashed #98a2b3; border-radius: 4px; text-align: center; }
        .inspection-heading { height: 4mm; color: #344054; font-size: 6.8px; font-weight: bold; line-height: 4mm; }
        .diagram-holder { width: 100%; height: 34mm; border-collapse: collapse; }
        .diagram-holder td { width: 100%; padding: 0; text-align: center; vertical-align: middle; }
        .damage-diagram { display: inline-block; width: auto; height: 33mm; margin: 0 auto; }
        .note-line { margin-top: 4px; border-bottom: 1px dotted #98a2b3; }
        .signatures { margin-top: 5px; }
        .signature { height: 20mm; padding: 5px; border: 1px solid #d0d5dd; border-radius: 4px; }
        .signature:first-child { margin-right: 3px; }
        .signature:last-child { margin-left: 3px; }
        .signature-title { color: {{ $agency->primary_color ?: '#f97316' }}; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .signature-hint { color: #667085; font-size: 6.5px; }
        .page-number { position: absolute; right: 0; bottom: 0; color: #98a2b3; font-size: 6.5px; }
        .conditions-title { margin: 9px 0 2px; color: #101828; font-size: 15px; letter-spacing: 1.8px; text-align: center; }
        .conditions { width: 98%; margin: 8px auto 0; table-layout: fixed; }
        .conditions td { width: 50%; padding: 0 9px; font-size: 8.3px; line-height: 1.38; overflow-wrap: break-word; word-wrap: break-word; }
        .conditions td:first-child { padding-left: 4px; padding-right: 10px; border-right: 1px solid #eaecf0; }
        .conditions td:last-child { padding-right: 4px; padding-left: 10px; }
        .clause { margin-bottom: 6px; text-align: left; }
        .clause-title { display: block; margin-bottom: 1px; color: {{ $agency->primary_color ?: '#f97316' }}; font-size: 8.4px; font-weight: bold; }
        .acknowledgement { margin-top: 6px; padding: 6px; border: 1px solid {{ $agency->primary_color ?: '#f97316' }}; border-radius: 4px; background: #fffaf5; }
        .conditions-signature { width: 48%; height: 27mm; margin-top: 7px; padding: 5px; border: 1px solid #d0d5dd; border-radius: 4px; }
        .footer { position: absolute; bottom: 0; left: 0; width: 100%; padding-top: 3px; border-top: 1px solid #eaecf0; color: #667085; font-size: 6px; }
    </style>
</head>
<body>
@php
    $primary = $agency->primary_color ?: '#f97316';
    $logoFile = $agency->files()->where('collection', 'logo')->first();
    $storedLogo = $logoFile?->path ? str_replace('storage/', '', $logoFile->path) : null;
    $logoPath = $storedLogo && is_file(storage_path('app/public/'.$storedLogo))
        ? storage_path('app/public/'.$storedLogo)
        : public_path('logo/logo.png');
    $damageDiagramPath = public_path('images/contracts/vehicle-damage-diagram.png');
    $startFuel = $reservation->fuelTankRecords->firstWhere('record_type', \App\Models\FuelTankRecord::AT_RENTAL_START);
    $endFuel = $reservation->fuelTankRecords->firstWhere('record_type', \App\Models\FuelTankRecord::AT_RENTAL_END);
    $paid = $reservation->payments->filter(fn ($payment) => ($payment->status->value ?? $payment->status) === 'completed')->sum('amount');
    $remaining = max(0, (float) $reservation->total_amount - (float) $paid);
    $secondaryDriver = $reservation->secondaryDriver;
    $money = fn ($amount) => number_format((float) $amount, 2, ',', ' ').' '.$currency;
    $date = fn ($value) => $value ? $value->format('d/m/Y') : '—';
    $time = fn ($value) => $value ? $value->format('H:i') : '—';
@endphp

<div class="page">
    <table class="brand-table">
        <tr>
            <td style="width:11%">
                @if(is_file($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="Logo">
                @endif
            </td>
            <td class="brand-details" style="width:56%">
                <div class="brand-name">{{ $agency->trading_name }}</div>
                <div class="legal-name">{{ $agency->legal_name ?: $agency->trading_name }}</div>
                <div class="eyebrow">Location de véhicules • Algérie</div>
                @if($agency->phone || $agency->secondary_phone)
                    <div class="phones">
                        @if($agency->phone)<span class="phone">Tél. {{ $agency->phone }}</span>@endif
                        @if($agency->secondary_phone)<span class="phone">Tél. {{ $agency->secondary_phone }}</span>@endif
                    </div>
                @endif
            </td>
            <td class="contract-meta" style="width:33%">
                <div class="contract-number">CONTRAT N° {{ $reservation->reservation_number }}</div>
                <div>Fait à {{ $agency->city ?: '________________' }}, le {{ now()->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <h1>CONTRAT DE LOCATION DE VÉHICULE</h1>
    <div class="subtitle">Document contractuel entre l’agence et le locataire</div>

    <div class="section">
        <div class="section-title">Informations de l’agence (bailleur)</div>
        <div class="section-body">
            <table class="data-grid"><tr>
                <td><span class="label">Raison sociale</span><span class="value">{{ $agency->legal_name ?: $agency->trading_name }}</span></td>
                <td><span class="label">RC / RNC N°</span><span class="value">{{ $agency->registration_number ?: '—' }}</span></td>
                <td><span class="label">NIF N°</span><span class="value">{{ $agency->tax_number ?: '—' }}</span></td>
                <td><span class="label">Téléphone</span><span class="value">{{ collect([$agency->phone, $agency->secondary_phone])->filter()->implode(' / ') ?: '—' }}</span></td>
            </tr></table>
            <span class="label">Adresse du siège</span><span class="value">{{ collect([$agency->address_line_1, $agency->address_line_2, $agency->postal_code, $agency->city, $agency->country])->filter()->implode(', ') ?: '—' }}</span>
        </div>
    </div>

    <table class="columns"><tr>
        <td style="width:50%">
            <div class="section">
                <div class="section-title">Client (locataire)</div>
                <div class="section-body">
                    <div class="field"><span class="label">Nom et prénom</span><span class="value">{{ $reservation->user->name ?? '—' }}</span></div>
                    <table class="data-grid"><tr>
                        <td><div class="field"><span class="label">Date de naissance</span><span class="value">{{ optional($reservation->user?->birth_date)->format('d/m/Y') ?: '—' }}</span></div></td>
                        <td><div class="field"><span class="label">Téléphone</span><span class="value">{{ $reservation->user->phone ?? '—' }}</span></div></td>
                    </tr></table>
                    <div class="field"><span class="label">E-mail</span><span class="value">{{ $reservation->user->email ?? '—' }}</span></div>
                    <div class="field"><span class="label">Permis de conduire N°</span><span class="value">{{ $reservation->user->driving_license_number ?? '—' }}</span></div>
                    <table class="data-grid"><tr>
                        <td><div class="field"><span class="label">Délivré le</span><span class="value">{{ optional($reservation->user?->driving_license_delivered_at)->format('d/m/Y') ?: '—' }}</span></div></td>
                        <td><div class="field"><span class="label">Autorité de délivrance</span><span class="value">{{ $reservation->user->driving_license_authority ?? '—' }}</span></div></td>
                    </tr></table>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Conducteur supplémentaire</div>
                <div class="section-body">
                    @if($secondaryDriver)
                        <div class="field"><span class="label">Nom et prénom</span><span class="value">{{ $secondaryDriver->full_name }}</span></div>
                        <table class="data-grid"><tr>
                            <td><div class="field"><span class="label">Téléphone</span><span class="value">{{ $secondaryDriver->phone }}</span></div></td>
                            <td><div class="field"><span class="label">E-mail</span><span class="value">{{ $secondaryDriver->email ?: '—' }}</span></div></td>
                        </tr></table>
                        <span class="label">Permis</span><span class="value">Copie vérifiée et approuvée par l’agence</span>
                    @else
                        <span class="muted">Aucun conducteur supplémentaire affecté.</span>
                    @endif
                </div>
            </div>
        </td>
        <td style="width:50%">
            <div class="section">
                <div class="section-title">Véhicule</div>
                <div class="section-body">
                    <table class="data-grid"><tr>
                        <td><div class="field"><span class="label">Marque et modèle</span><span class="value">{{ $reservation->car ? $reservation->car->make.' '.$reservation->car->model : '—' }}</span></div></td>
                        <td><div class="field"><span class="label">Immatriculation</span><span class="value">{{ $reservation->car->license_plate ?? '—' }}</span></div></td>
                    </tr><tr>
                        <td><div class="field"><span class="label">Année</span><span class="value">{{ $reservation->car->year ?? '—' }}</span></div></td>
                        <td><div class="field"><span class="label">Couleur</span><span class="value">{{ $reservation->car?->color?->value ?? $reservation->car?->color ?? '—' }}</span></div></td>
                    </tr><tr>
                        <td><div class="field"><span class="label">KM au départ</span><span class="value">{{ $reservation->car->mileage ?? '—' }}</span></div></td>
                        <td><div class="field"><span class="label">KM au retour</span><span class="value">________________</span></div></td>
                    </tr></table>
                    <div class="field"><span class="label">Carburant au départ</span><span class="value">{{ $startFuel ? $startFuel->fuel_level.' %' : 'Non renseigné' }}</span></div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Période de location</div>
                <div class="section-body">
                    <table class="data-grid"><tr>
                        <td><div class="field"><span class="label">Départ (date / heure)</span><span class="value">{{ $date($reservation->start_date) }} · {{ $time($reservation->pickup_time) }}</span></div></td>
                        <td><div class="field"><span class="label">Lieu</span><span class="value">{{ $reservation->pickup_location ?: '—' }}</span></div></td>
                    </tr><tr>
                        <td><div class="field"><span class="label">Retour (date / heure)</span><span class="value">{{ $date($reservation->end_date) }} · {{ $time($reservation->return_time) }}</span></div></td>
                        <td><div class="field"><span class="label">Lieu</span><span class="value">{{ $reservation->return_location ?: '—' }}</span></div></td>
                    </tr></table>
                    <span class="label">Nombre de jours</span><span class="value">{{ $reservation->total_days }} jour(s)</span>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Paiement et caution</div>
                <div class="section-body">
                    <table class="money-table">
                        <tr><td>Tarif journalier × {{ $reservation->total_days }}</td><td>{{ $money($reservation->daily_rate) }}</td></tr>
                        @if((float) $reservation->tax_amount > 0)<tr><td>Taxe</td><td>{{ $money($reservation->tax_amount) }}</td></tr>@endif
                        <tr class="money-total"><td>Montant total location</td><td>{{ $money($reservation->total_amount) }}</td></tr>
                        <tr><td>Caution / dépôt de garantie</td><td>{{ $money($reservation->security_deposit_amount) }}</td></tr>
                        <tr><td>Versements reçus</td><td>{{ $money($paid) }}</td></tr>
                        <tr><td>Reste à payer</td><td>{{ $money($remaining) }}</td></tr>
                    </table>
                </div>
            </div>
        </td>
    </tr></table>

    <div class="section">
        <div class="section-title">État des lieux du véhicule et observations</div>
        <div class="section-body">
            <table class="inspection"><tr>
                <td><div class="inspection-box"><div class="inspection-heading">ÉTAT INITIAL — DÉPART</div><table class="diagram-holder"><tr><td>@if(is_file($damageDiagramPath))<img class="damage-diagram" src="{{ $damageDiagramPath }}" alt="Schéma des dommages du véhicule">@endif</td></tr></table></div></td>
                <td><div class="inspection-box"><div class="inspection-heading">ÉTAT FINAL — RETOUR</div><table class="diagram-holder"><tr><td>@if(is_file($damageDiagramPath))<img class="damage-diagram" src="{{ $damageDiagramPath }}" alt="Schéma des dommages du véhicule">@endif</td></tr></table></div></td>
            </tr></table>
            <div class="muted" style="text-align:center">X = Rayure • O = Choc / Bosse • △ = Fissure / Cassé • annoter en présence du client</div>
        </div>
    </div>

    <table class="signatures"><tr>
        <td class="signature"><div class="signature-title">Signature du client</div><div class="signature-hint">Précédée de la mention « Lu et approuvé, bon pour accord »</div></td>
        <td class="signature"><div class="signature-title">Signature et cachet de l’agence</div><div class="signature-hint">Responsable habilité — date, signature et cachet</div></td>
    </tr></table>
    <div class="page-number">Page 1 / 2</div>
</div>

<div class="page page-break">
    <table class="brand-table"><tr>
        <td style="width:11%">@if(is_file($logoPath))<img class="logo" src="{{ $logoPath }}" alt="Logo">@endif</td>
        <td class="brand-details" style="width:56%">
            <div class="brand-name">{{ $agency->trading_name }}</div>
            <div class="legal-name">{{ $agency->legal_name ?: $agency->trading_name }}</div>
            @if($agency->phone || $agency->secondary_phone)<div class="phones">@if($agency->phone)<span class="phone">Tél. {{ $agency->phone }}</span>@endif @if($agency->secondary_phone)<span class="phone">Tél. {{ $agency->secondary_phone }}</span>@endif</div>@endif
        </td>
        <td class="contract-meta" style="width:33%"><div class="contract-number">CONTRAT N° {{ $reservation->reservation_number }}</div><div>PAGE 2 / 2</div></td>
    </tr></table>
    <div class="conditions-title">CONDITIONS GÉNÉRALES DE LOCATION</div>
    <div class="subtitle">À lire attentivement avant signature</div>

    <table class="conditions"><tr>
        <td>
            <div class="clause"><span class="clause-title">1. Principe</span>La prise en charge du véhicule par le locataire implique l’acceptation sans réserve des présentes conditions générales de location.</div>
            <div class="clause"><span class="clause-title">2. Conditions de location</span>Le locataire doit être titulaire d’un permis de conduire valide et présenter une pièce d’identité ou un passeport en cours de validité. Une caution peut être exigée à la signature du contrat.</div>
            <div class="clause"><span class="clause-title">3. État du véhicule</span>Le véhicule est remis en bon état de fonctionnement. Un état des lieux est établi au départ et au retour. Toute dégradation constatée et non indiquée au départ pourra être facturée au locataire.</div>
            <div class="clause"><span class="clause-title">4. Utilisation du véhicule</span>Le locataire s’engage à utiliser le véhicule avec soin, à respecter le Code de la route, à ne pas l’utiliser à des fins illicites, à ne pas le sous-louer, ni le prêter à un conducteur non approuvé par le loueur.</div>
            <div class="clause"><span class="clause-title">5. Durée de location</span>La durée minimale de location est de 24 heures. Toute journée entamée est due. Aucun remboursement n’est accordé en cas de restitution anticipée, sauf accord écrit du loueur.</div>
            <div class="clause"><span class="clause-title">6. Restitution du véhicule</span>Le véhicule doit être restitué à la date, à l’heure et au lieu convenus. Tout retard peut entraîner une facturation supplémentaire selon les tarifs en vigueur.</div>
            <div class="clause"><span class="clause-title">7. Prolongation de la location</span>Toute prolongation doit être demandée avant l’expiration du contrat et acceptée par le loueur. Elle n’est effective qu’après règlement des frais correspondants.</div>
            <div class="clause"><span class="clause-title">8. Réservation et annulation</span>Toute réservation peut nécessiter le versement d’un acompte. En cas d’annulation tardive, l’acompte pourra être conservé conformément aux conditions communiquées lors de la réservation.</div>
        </td>
        <td>
            <div class="clause"><span class="clause-title">9. Carburant</span>Le véhicule est remis avec un niveau de carburant déterminé et doit être restitué avec le même niveau. À défaut, le carburant manquant pourra être facturé.</div>
            <div class="clause"><span class="clause-title">10. Entretien et réparation</span>L’entretien normal est assuré par le loueur. Les réparations dues à une mauvaise utilisation, une négligence ou un accident imputable au locataire seront à sa charge.</div>
            <div class="clause"><span class="clause-title">11. Assurances</span>Le véhicule est assuré conformément à la réglementation en vigueur. Le locataire doit déclarer immédiatement tout sinistre et fournir les documents nécessaires. Les exclusions du contrat d’assurance restent applicables.</div>
            <div class="clause"><span class="clause-title">12. Responsabilité</span>Le locataire est responsable des dommages, pertes, vols, infractions et amendes survenus pendant la durée de la location, sauf lorsqu’ils sont couverts par l’assurance.</div>
            <div class="clause"><span class="clause-title">13. Interdictions</span>Le transport de produits dangereux ou illicites, la participation à des compétitions, la conduite sous l’emprise d’alcool ou de stupéfiants et la sortie du territoire sans autorisation écrite sont interdits.</div>
            <div class="clause"><span class="clause-title">14. Caution</span>La caution est restituée après vérification de l’état du véhicule et règlement des sommes dues. Le loueur peut en conserver tout ou partie pour couvrir réparations, nettoyage, franchises, carburant, amendes ou autres créances.</div>
            <div class="clause"><span class="clause-title">15. Droit applicable et juridiction compétente</span>Le présent contrat est régi par le droit algérien. Tout litige relatif à son interprétation ou à son exécution relève des tribunaux territorialement compétents du ressort du siège social du loueur.</div>
        </td>
    </tr></table>

    <div class="acknowledgement">Le locataire reconnaît avoir lu et accepté l’intégralité des présentes conditions générales, qui font partie intégrante du contrat signé en page 1.</div>
    <div class="conditions-signature"><div class="signature-title">Signature du client</div><div class="signature-hint">Précédée de la mention « Lu et accepté »<br><br>Date et signature :</div></div>

    <div class="footer">
        {{ $agency->legal_name ?: $agency->trading_name }}
        @if($agency->registration_number) • RC {{ $agency->registration_number }} @endif
        @if($agency->phone) • {{ $agency->phone }} @endif
        @if($agency->email) • {{ $agency->email }} @endif
    </div>
</div>
</body>
</html>
