<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $language === 'ar' ? 'شهادة مدرسية' : 'Certificat de scolarité' }}</title>
    <style>
        @page { margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #111; font-size: 13px; line-height: 1.55; }
        /* Dompdf does not consistently apply border-box sizing. Keep the
           declared height plus padding safely below the 267 mm print area. */
        .page { border: 1px solid #111; height: 235mm; padding: 11mm 14mm; position: relative; overflow: hidden; }
        .header { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { max-width: 72px; max-height: 64px; }
        .school { text-align: center; }
        .school-name { color: #111; font-size: 19px; font-weight: bold; }
        .legal-name { margin-top: 1px; font-size: 9px; }
        .school-details { color: #111; font-size: 9px; line-height: 1.45; }
        .rule { border: 0; border-top: 1px solid #111; margin: 11px 0 21px; }
        h1 { color: #111; font-size: 24px; text-align: center; text-transform: uppercase; letter-spacing: .7px; margin: 0 0 22px; }
        .reference { color: #111; font-size: 10px; margin-bottom: 18px; }
        .body { font-size: 15px; line-height: 1.9; text-align: justify; }
        .value { font-weight: bold; color: #111; border-bottom: 1px dotted #111; padding: 0 5px; }
        .notice { margin-top: 20px; font-size: 12px; color: #111; }
        .issued { margin-top: 30px; text-align: {{ $language === 'ar' ? 'left' : 'right' }}; }
        .signature { margin-top: 34px; font-weight: bold; }
        .footer { position: absolute; bottom: 8mm; left: 15mm; right: 15mm; border-top: 1px solid #111; padding-top: 6px; color: #111; font-size: 8px; line-height: 1.5; text-align: center; }
        .rtl { direction: ltr; text-align: right; }
        .arabic-title { text-transform: none; letter-spacing: 0; }
        .arabic-details { width: 100%; border-collapse: collapse; font-size: 15px; }
        .arabic-details td { padding: 8px 6px; vertical-align: baseline; border-bottom: 1px dotted #111; }
        .arabic-details .label { width: 37%; color: #111; text-align: right; }
        .arabic-details .detail-value { color: #111; font-weight: bold; text-align: right; }
        .arabic-text { direction: ltr; unicode-bidi: bidi-override; text-align: right; }
    </style>
</head>
<body>
@php($issuePlace = $school->city ?: '........................')
<div class="page {{ $language === 'ar' ? 'rtl' : '' }}">
    <table class="header">
        <tr>
            <td style="width: 95px">@if($schoolLogo)<img class="logo" src="{{ $schoolLogo }}" alt="">@endif</td>
            <td class="school">
                <div class="school-name">{{ $school->trading_name ?: $school->legal_name ?: config('app.name') }}</div>
                @if($school->legal_name && $school->legal_name !== $school->trading_name)
                    <div class="legal-name">{{ $school->legal_name }}</div>
                @endif
                <div class="school-details">
                    @php($address = collect([$school->address_line_1, $school->address_line_2, $school->postal_code, $school->city, $school->country])->filter()->join(', '))
                    @if($address){{ $address }}<br>@endif
                    {{ collect([$school->phone, $school->secondary_phone])->filter()->join(' / ') }}
                    @if(($school->phone || $school->secondary_phone) && $school->email) · @endif
                    @if($school->email){{ $school->email }}@endif
                    @if($school->website)<br>{{ $school->website }}@endif
                </div>
            </td>
            <td style="width: 95px"></td>
        </tr>
    </table>
    <hr class="rule">

    @if($language === 'ar')
        <h1 class="arabic-title arabic-text">{{ $ar('شهادة مدرسية') }}</h1>
        <div class="reference arabic-text">{{ $enrollment->academicYear->name }} / {{ str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT) }} :{{ $ar('المرجع') }}</div>
        <p class="body arabic-text">{{ $ar('يشهد مدير المؤسسة أن التلميذ المسجل أدناه يزاول دراسته بصفة قانونية:') }}</p>
        <table class="arabic-details">
            <tr><td class="detail-value">{{ $school->trading_name ?: $school->legal_name ?: config('app.name') }}</td><td class="label arabic-text">{{ $ar('المؤسسة') }}</td></tr>
            <tr><td class="detail-value">{{ $enrollment->student->full_name }}</td><td class="label arabic-text">{{ $ar('اسم ولقب التلميذ') }}</td></tr>
            @if($enrollment->student->birth_date)
                <tr><td class="detail-value">{{ $enrollment->student->birth_date->format('d/m/Y') }}</td><td class="label arabic-text">{{ $ar('تاريخ الميلاد') }}</td></tr>
            @endif
            <tr><td class="detail-value">{{ $enrollment->academicYear->name }}</td><td class="label arabic-text">{{ $ar('السنة الدراسية') }}</td></tr>
            <tr><td class="detail-value">{{ $enrollment->level->name }}</td><td class="label arabic-text">{{ $ar('المستوى') }}</td></tr>
            @if($enrollment->stream)
                <tr><td class="detail-value arabic-text">{{ $ar($enrollment->stream->name_ar ?: $enrollment->stream->name_fr) }}</td><td class="label arabic-text">{{ $ar('الشعبة') }}</td></tr>
            @endif
            <tr><td class="detail-value">{{ $enrollment->group->name }}</td><td class="label arabic-text">{{ $ar('القسم') }}</td></tr>
        </table>
        <div class="notice arabic-text">{{ $ar('سُلّمت هذه الشهادة للمعني لاستعمالها فيما يسمح به القانون.') }}</div>
        <div class="issued arabic-text">
            {{ \Carbon\Carbon::parse($issueDate)->format('d/m/Y') }} {{ $ar('بتاريخ') }} {{ $ar($issuePlace) }} {{ $ar('حررت في') }}
            <div class="signature">{{ $ar('الإدارة') }}</div>
        </div>
    @else
        <h1>Certificat de scolarité</h1>
        <div class="reference">Référence : {{ $enrollment->academicYear->name }} / {{ str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="body">
            Je soussigné(e), directeur/directrice de l’établissement
            <span class="value">{{ $school->trading_name ?: $school->legal_name ?: config('app.name') }}</span>,
            certifie que l’élève <span class="value">{{ $enrollment->student->full_name }}</span>
            @if($enrollment->student->birth_date)
                né(e) le <span class="value">{{ $enrollment->student->birth_date->format('d/m/Y') }}</span>,
            @endif
            est régulièrement inscrit(e) au titre de l’année scolaire
            <span class="value">{{ $enrollment->academicYear->name }}</span>, au niveau
            <span class="value">{{ $enrollment->level->name }}</span>
            @if($enrollment->stream)
                , filière <span class="value">{{ $enrollment->stream->name_fr }}</span>
            @endif
            , groupe <span class="value">{{ $enrollment->group->name }}</span>.
        </div>
        <div class="notice">Le présent certificat est délivré à l’intéressé(e) pour servir et valoir ce que de droit.</div>
        <div class="issued">
            Fait à {{ $issuePlace }}, le {{ \Carbon\Carbon::parse($issueDate)->format('d/m/Y') }}
            <div class="signature">La direction</div>
        </div>
    @endif

    <div class="footer">
        {{ $school->legal_name ?: $school->trading_name }}<br>
        @if($school->registration_number)RC / agrément : {{ $school->registration_number }}@endif
        @if($school->tax_number){{ $school->registration_number ? ' · ' : '' }}NIF : {{ $school->tax_number }}@endif
        @if($school->nis){{ ($school->registration_number || $school->tax_number) ? ' · ' : '' }}NIS : {{ $school->nis }}@endif
        @if($school->cnas_employer_number){{ ($school->registration_number || $school->tax_number || $school->nis) ? ' · ' : '' }}CNAS : {{ $school->cnas_employer_number }}@endif
    </div>
</div>
</body>
</html>
