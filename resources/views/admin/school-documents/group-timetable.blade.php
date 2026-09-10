<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Emploi du temps - {{ $group?->name ?: $teacher->name }} - {{ $group?->academicYear?->name ?: $year->name }}</title>
    <style>
        @page { margin: 7mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 7px; }
        .header { width: 100%; margin-bottom: 4mm; border-collapse: collapse; border-bottom: 1px solid #111; }
        .header td { padding: 0 0 3mm; vertical-align: middle; }
        .brand { width: 30%; }
        .logo { max-width: 18mm; max-height: 14mm; margin-right: 3mm; vertical-align: middle; }
        .school-name { display: inline-block; font-size: 12px; font-weight: bold; vertical-align: middle; }
        .title { width: 40%; text-align: center; }
        .title h1 { margin: 0; font-size: 15px; text-transform: uppercase; }
        .title p { margin: 1mm 0 0; font-size: 9px; }
        .details { width: 30%; line-height: 1.55; text-align: right; }
        .details strong { font-size: 8px; }
        .schedule { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .schedule th, .schedule td { border: .6px solid #333; text-align: center; vertical-align: middle; }
        .schedule thead th { height: 9mm; padding: 1.5mm 1mm; background: #eee; font-size: 8px; }
        .schedule .time { width: 20mm; padding: 1mm; font-size: 7px; font-weight: bold; }
        .schedule tbody td { height: {{ max(8, min(15, 135 / max(count($slots), 1))) }}mm; padding: 1mm; }
        .session { margin: .5mm 0; padding: .8mm; border: .5px solid #777; line-height: 1.35; page-break-inside: avoid; }
        .subject { display: block; font-size: 7.5px; font-weight: bold; }
        .meta { display: block; margin-top: .5mm; font-size: 6px; }
        .break { font-style: italic; }
        .empty { color: #777; }
        .footer { position: fixed; right: 0; bottom: -3mm; left: 0; font-size: 6px; text-align: center; }
    </style>
</head>
<body>
@php
    $dayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
    $shortTime = fn ($value) => substr((string) $value, 0, 5);
    $academicYear = $group?->academicYear ?: $year;
    $entityName = $group?->name ?: $teacher->name;
@endphp
<table class="header">
    <tr>
        <td class="brand">
            @if($schoolLogo)<img class="logo" src="{{ $schoolLogo }}" alt="">@endif
            <span class="school-name">{{ $school->trading_name ?: $school->legal_name ?: config('app.name') }}</span>
        </td>
        <td class="title">
            <h1>Emploi du temps hebdomadaire</h1>
            <p>Année scolaire {{ $academicYear->name }}</p>
        </td>
        <td class="details">
            <strong>{{ $entityName }}</strong><br>
            @if($group)
                {{ $group->level?->cycle?->name }} · {{ $group->level?->name }}
                @if($group->level?->specialization) · {{ $group->level->specialization }}@endif<br>
                Salle : {{ $group->classroom?->name ?: '—' }} · Prof. principal : {{ $group->principalTeacher?->name ?: '—' }}
            @else
                Enseignant<br>{{ $teacher->email }}
            @endif
        </td>
    </tr>
</table>

<table class="schedule">
    <thead>
        <tr>
            <th class="time">Horaire</th>
            @foreach($days as $day)<th>{{ $dayNames[$day] }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($slots as $slot)
            @php
                $slotStart = $slot['start_time'] ?? $slot['start'];
                $slotEnd = $slot['end_time'] ?? $slot['end'];
                $activeBreak = collect($breaks)->first(fn ($break) => ($break['start_time'] ?? $break['start']) < $slotEnd && ($break['end_time'] ?? $break['end']) > $slotStart);
            @endphp
            <tr>
                <td class="time">{{ $shortTime($slotStart) }}<br>{{ $shortTime($slotEnd) }}</td>
                @foreach($days as $day)
                    <td>
                        @if($activeBreak)
                            <span class="break">{{ $activeBreak['name'] ?? 'Pause' }}</span>
                        @else
                            @php($cellSessions = $sessions->filter(fn ($session) => $session->day === $day && $shortTime($session->start_time) >= $shortTime($slotStart) && $shortTime($session->start_time) < $shortTime($slotEnd)))
                            @forelse($cellSessions as $session)
                                <div class="session">
                                    <span class="subject">{{ $session->subject?->title ?: $session->subject?->name ?: 'Matière' }}</span>
                                    <span class="meta">{{ $shortTime($session->start_time) }}–{{ $shortTime($session->end_time) }}</span>
                                    <span class="meta">{{ $group ? ($session->teacher?->name ?: '—') : ($session->group?->name ?: '—') }} · {{ $session->room?->name ?: '—' }}</span>
                                </div>
                            @empty
                                <span class="empty">—</span>
                            @endforelse
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    {{ $school->legal_name ?: $school->trading_name }} · {{ $entityName }} · {{ $academicYear->name }}
</div>
</body>
</html>
