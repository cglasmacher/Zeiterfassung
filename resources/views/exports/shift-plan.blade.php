<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>
@page { margin: 12mm; } body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #172741; }
h1 { font-size: 21px; margin-bottom: 3px; } h2 { font-size: 14px; } .section { page-break-before: always; } .section.first { page-break-before: auto; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; } th, td { border: 1px solid #cbd2dd; padding: 6px; vertical-align: top; overflow-wrap: break-word; } th { background: #172741; color: white; } .day { width: 100px; } tr { page-break-inside: avoid; } thead { display: table-header-group; } .shift { padding: 3px 0; } .time { color: #555; font-size: 9px; } .footer { color: #666; font-size: 8px; margin-top: 12px; }
</style></head><body>
@if($sections->isEmpty())<h1>Hemingway · Dienstplan</h1><p>Keine Schichten für diesen Zeitraum geplant.</p>@endif
@foreach($sections as $section)
{{-- Limit the number of columns per page; a large set of shift types remains legible. --}}
@foreach($section['types']->chunk(6) as $types)
<div class="section {{ $loop->parent->first && $loop->first ? 'first' : '' }}">
<h1>Hemingway · Dienstplan</h1><p>{{ $weekStart->format('d.m.Y') }} bis {{ $weekEnd->format('d.m.Y') }} · KW {{ $weekStart->isoWeek() }}</p>
<h2>{{ $section['name'] }} @if($section['types']->count() > 6) · Teil {{ $loop->iteration }}@endif</h2>
<table><thead><tr><th class="day">Tag</th>@foreach($types as $type)<th>{{ $type->name }}<br><small>{{ substr($type->default_start, 0, 5) }} - {{ substr($type->default_end, 0, 5) }}</small></th>@endforeach</tr></thead><tbody>
@foreach($days as $day)<tr><td><strong>{{ $day->locale('de')->isoFormat('dddd') }}</strong><br>{{ $day->format('d.m.') }}</td>
@foreach($types as $type)<td>
@forelse($section['shifts']->filter(fn($shift) => $shift->shift_type_id == $type->id && \Carbon\Carbon::parse($shift->shift_date)->isSameDay($day)) as $shift)
<div class="shift"><strong>{{ $shift->employee ? $shift->employee->full_name : 'Offen' }}</strong><br><span class="time">{{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}</span></div>
@empty<span>-</span>@endforelse
</td>@endforeach</tr>@endforeach
</tbody></table><p class="footer">Erstellt {{ now()->format('d.m.Y H:i') }} · Tatsächlich geplante Zeiten je Mitarbeiter</p>
</div>@endforeach @endforeach
</body></html>
