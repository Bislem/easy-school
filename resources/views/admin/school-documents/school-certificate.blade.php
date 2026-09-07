<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $language === 'ar' ? 'شهادة مدرسية' : 'Certificat de scolarité' }}</title>
    <style>
        @page { margin: 24mm 22mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #172033; font-size: 14px; line-height: 1.8; }
        .page { border: 2px solid #1f4c7d; min-height: 235mm; padding: 13mm 15mm; position: relative; }
        .header { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { width: 82px; max-height: 82px; }
        .school { text-align: center; }
        .school-name { color: #163d69; font-size: 20px; font-weight: bold; }
        .school-details { color: #596579; font-size: 10px; line-height: 1.5; }
        .rule { border: 0; border-top: 2px solid #1f4c7d; margin: 15px 0 29px; }
        h1 { color: #163d69; font-size: 27px; text-align: center; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 30px; }
        .reference { color: #687386; font-size: 11px; margin-bottom: 22px; }
        .body { font-size: 16px; line-height: 2.15; text-align: justify; }
        .value { font-weight: bold; color: #0e3158; border-bottom: 1px dotted #8491a3; padding: 0 5px; }
        .notice { margin-top: 24px; font-size: 13px; color: #4c586a; }
        .issued { margin-top: 38px; text-align: {{ $language === 'ar' ? 'left' : 'right' }}; }
        .signature { margin-top: 45px; font-weight: bold; }
        .footer { position: absolute; bottom: 10mm; left: 15mm; right: 15mm; border-top: 1px solid #c7ced8; padding-top: 7px; color: #6b7280; font-size: 9px; text-align: center; }
        .rtl { direction: rtl; text-align: right; }
    </style>
</head>
<body>
<div class="page {{ $language === 'ar' ? 'rtl' : '' }}">
    <table class="header">
        <tr>
            <td style="width: 95px">@if($schoolLogo)<img class="logo" src="{{ $schoolLogo }}" alt="">@endif</td>
            <td class="school">
                <div class="school-name">{{ $school->trading_name ?: $school->legal_name ?: config('app.name') }}</div>
                <div class="school-details">
                    {{ collect([$school->address_line_1, $school->address_line_2, $school->postal_code, $school->city])->filter()->join(' · ') }}
                    @if($school->phone)<br>{{ $school->phone }}@endif
                    @if($school->email) · {{ $school->email }}@endif
                </div>
            </td>
            <td style="width: 95px"></td>
        </tr>
    </table>
    <hr class="rule">

    @if($language === 'ar')
        <h1>شهادة مدرسية</h1>
        <div class="reference">المرجع: {{ $enrollment->academicYear->name }} / {{ str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="body">
            يشهد مدير(ة) مؤسسة <span class="value">{{ $school->trading_name ?: $school->legal_name ?: config('app.name') }}</span>
            أن التلميذ(ة) <span class="value">{{ $enrollment->student->full_name }}</span>
            @if($enrollment->student->birth_date)
                المولود(ة) بتاريخ <span class="value">{{ $enrollment->student->birth_date->format('d/m/Y') }}</span>
            @endif
            مسجل(ة) بصفة قانونية خلال السنة الدراسية
            <span class="value">{{ $enrollment->academicYear->name }}</span>، في مستوى
            <span class="value">{{ $enrollment->level->name }}</span>
            @if($enrollment->stream)
                ، الشعبة <span class="value">{{ $enrollment->stream->name_ar ?: $enrollment->stream->name_fr }}</span>
            @endif
            ، القسم <span class="value">{{ $enrollment->group->name }}</span>.
        </div>
        <div class="notice">سُلّمت هذه الشهادة للمعني(ة) لاستعمالها فيما يسمح به القانون.</div>
        <div class="issued">
            حررت في {{ $school->city ?: '........................' }} بتاريخ {{ \Carbon\Carbon::parse($issueDate)->format('d/m/Y') }}
            <div class="signature">الإدارة</div>
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
            Fait à {{ $school->city ?: '........................' }}, le {{ \Carbon\Carbon::parse($issueDate)->format('d/m/Y') }}
            <div class="signature">La direction</div>
        </div>
    @endif

    <div class="footer">
        {{ $school->legal_name ?: $school->trading_name }}
        @if($school->registration_number) · RC/agrément : {{ $school->registration_number }}@endif
        @if($school->website) · {{ $school->website }}@endif
    </div>
</div>
</body>
</html>
