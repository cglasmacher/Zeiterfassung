<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 13mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11px; }
        h1 { font-family: DejaVu Serif, serif; font-size: 30px; font-weight: normal; text-align: center; margin: 0 0 2mm; }
        .period { text-align: center; font-size: 23px; font-weight: normal; margin: 0 0 4mm; }
        .page { page-break-before: always; }
        .page.first { page-break-before: auto; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1.5pt solid #111; text-align: center; vertical-align: middle; padding: 4px; overflow-wrap: break-word; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .groups th { background: #bfcc89; height: 28px; font-size: 12px; }
        .areas th, .day { background: #d8ddc5; }
        .areas th { height: 37px; }
        .day { font-weight: bold; }
        .date { font-weight: normal; font-size: 8px; }
        tbody td { height: {{ 440 / max(1, $days->count()) - 9 }}px; }
        .time { font-size: 9px; font-weight: normal; white-space: nowrap; }
        .actual-time { font-size: 8px; margin-top: 3px; }
        .dense { font-size: 9px; }
        .dense .areas th { font-size: 8px; }
        .dense .time, .dense .actual-time { font-size: 7px; }
        .footer { font-size: 7px; color: #666; text-align: right; margin-top: 3mm; }
        .empty { text-align: center; margin-top: 12mm; }
    </style>
</head>
<body>
@forelse($pages as $columns)
    <div class="page {{ $loop->first ? 'first' : '' }}">
        <h1>Dienstplan Hemingway</h1>
        <p class="period">{{ $weekStart->format('d.m.') }} - {{ $weekEnd->format('d.m.') }}</p>
        <table class="{{ $columns->count() > 10 ? 'dense' : '' }}">
            <colgroup>
                <col style="width: 13%;">
                @foreach($columns as $column)<col style="width: {{ 87 / $columns->count() }}%;">@endforeach
            </colgroup>
            <thead>
                <tr class="groups">
                    <th>Tag</th>
                    @foreach($columns->groupBy(fn ($column) => $column['type']->id) as $group)
                        <th colspan="{{ $group->count() }}">{{ $group->first()['type']->name }}</th>
                    @endforeach
                </tr>
                <tr class="areas">
                    <th>Bereich</th>
                    @foreach($columns as $column)
                        <th>{{ $column['department'] }}<br>
                            <span class="time">{{ substr($column['type']->default_start, 0, 5) }} - {{ substr($column['type']->default_end, 0, 5) }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($days as $day)
                    <tr>
                        <td class="day">{{ $day->locale('de')->isoFormat('dddd') }}<br><span class="date">{{ $day->format('d.m.') }}</span></td>
                        @foreach($columns as $column)
                            @php($shift = $column['assignments']->get($day->toDateString()))
                            <td>
                                @if($shift)
                                    {{ $shift->employee?->full_name ?? 'Offen' }}
                                    @if(substr($shift->start_time, 0, 5) !== substr($column['type']->default_start, 0, 5) || substr($shift->end_time, 0, 5) !== substr($column['type']->default_end, 0, 5))
                                        <div class="actual-time">{{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}</div>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="footer">{{ $weekStart->format('Y') }} · KW {{ $weekStart->isoWeek() }} · Erstellt {{ now()->format('d.m.Y H:i') }} · Seite {{ $loop->iteration }}/{{ $pages->count() }}</p>
    </div>
@empty
    <h1>Dienstplan Hemingway</h1>
    <p class="period">{{ $weekStart->format('d.m.') }} - {{ $weekEnd->format('d.m.') }}</p>
    <p class="empty">Keine Schichten für diesen Zeitraum angelegt.</p>
@endforelse
</body>
</html>
