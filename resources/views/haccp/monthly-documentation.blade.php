<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
@page { margin: 11mm 10mm 12mm; }
body { font-family: DejaVu Sans, sans-serif; color:#222; font-size:8px; }
h1 { font-size:18px; margin:0 0 4px; } h2 { font-size:12px; margin:14px 0 5px; }
.small { font-size:7px; color:#666; }.page-break { page-break-before:always; }
table { border-collapse:collapse; width:100%; } th,td { border:1px solid #bbb; padding:2px 3px; vertical-align:top; }
th { background:#eee; font-weight:bold; }.summary td { padding:5px; font-size:9px; }
.matrix { table-layout:fixed; font-size:5.7px; }.matrix th,.matrix td { padding:1.4px 1px; text-align:center; white-space:nowrap; }
.matrix .device { width:30mm; text-align:left; white-space:normal; font-size:6.3px; }.matrix .bad { font-weight:bold; background:#ddd; }.matrix .missing { font-weight:bold; }
.deviation { margin-bottom:7px; page-break-inside:avoid; }.footer { position:fixed; bottom:-7mm; left:0; right:0; font-size:6px; color:#777; text-align:center; }
</style>
</head><body>
<div class="footer">HACCP-Dokumentation · Hemingway Gastro & Events GmbH · {{ sprintf('%02d/%04d',$document['period']['month'],$document['period']['year']) }}</div>
<h1>HACCP-Monatsdokumentation</h1>
<div>Hemingway Gastro & Events GmbH · Markt 16 · 40721 Hilden</div>
<div class="small">Zeitraum {{ $document['period']['from'] }} bis {{ $document['period']['to'] }} · Dokument HACCP-{{ $document['period']['year'] }}-{{ sprintf('%02d',$document['period']['month']) }}</div>
<h2>Monatsübersicht</h2>
<table class="summary"><tr><th>Kontrollen</th><th>Erledigt</th><th>Versäumt</th><th>Erfüllung</th><th>Abweichungen</th><th>Offen</th></tr><tr>
<td>{{ $document['statistics']['total'] }}</td><td>{{ $document['statistics']['completed'] }}</td><td>{{ $document['statistics']['missing'] }}</td><td>{{ number_format($document['statistics']['completion_rate'],1,',','.') }} %</td><td>{{ $document['statistics']['deviations'] }}</td><td>{{ $document['statistics']['open_deviations'] }}</td>
</tr></table>

<h2>Temperaturkontrollen – kompakte Monatsmatrix</h2>
<div class="small">Mehrere Messungen eines Tages stehen in einer Zelle, getrennt durch „/“. „—“ = Kontrolle nicht durchgeführt. „!“ = Messwert außerhalb des Grenzwerts; Details im Abweichungsregister.</div>
<table class="matrix"><thead><tr><th class="device">Kühleinheit / Soll</th>@for($day=1;$day<=$document['period']['days'];$day++)<th>{{ $day }}</th>@endfor</tr></thead><tbody>
@forelse($document['temperature_matrix'] as $device=>$row)
<tr><td class="device"><strong>{{ $device }}</strong>@if($row['meta']['location'])<br>{{ $row['meta']['location'] }}@endif<br><span class="small">{{ $row['meta']['min'] ?? '–' }}–{{ $row['meta']['max'] ?? '–' }} {{ $row['meta']['unit'] }}</span></td>
@for($day=1;$day<=$document['period']['days'];$day++)
@php $entries=$row['days'][$day] ?? []; $hasBad=collect($entries)->contains(fn($e)=>$e['within_limits']===false); $hasMissing=collect($entries)->contains(fn($e)=>$e['missing']); @endphp
<td class="{{ $hasBad?'bad':'' }} {{ $hasMissing?'missing':'' }}">@if(empty($entries))·@else @foreach($entries as $i=>$entry)@if($i)/@endif @if($entry['missing'])—@else{{ rtrim(rtrim(number_format((float)$entry['value'],1,',',''),'0'),',') }}@if($entry['within_limits']===false)!@endif @endif @endforeach @endif</td>
@endfor</tr>
@empty<tr><td colspan="32">Keine Temperaturkontrollen in diesem Monat.</td></tr>@endforelse
</tbody></table>

@if(count($document['missing_controls']))
<h2>Versäumte Kontrollen</h2><table><tr><th>Fällig</th><th>Kontrolle</th><th>Gerät/Bereich</th><th>Kategorie</th></tr>@foreach($document['missing_controls'] as $item)<tr><td>{{ $item['due_at'] }}</td><td>{{ $item['title'] }}</td><td>{{ $item['device'] ?: '–' }}</td><td>{{ $item['category'] }}</td></tr>@endforeach</table>
@endif

@if(count($document['deviations']))
<div class="page-break"></div><h2>Abweichungs- und Maßnahmenregister</h2>
@foreach($document['deviations'] as $d)<div class="deviation"><table><tr><th style="width:25mm">Abweichung</th><td>A-{{ $document['period']['year'] }}-{{ sprintf('%02d',$document['period']['month']) }}-{{ str_pad($d['id'],3,'0',STR_PAD_LEFT) }}</td></tr><tr><th>Datum / Kontrolle</th><td>{{ $d['date'] }} · {{ $d['control'] }}@if($d['device']) · {{ $d['device'] }}@endif</td></tr><tr><th>Messwert</th><td>{{ $d['value'] ?? '–' }}</td></tr><tr><th>Feststellung</th><td>{{ $d['description'] }}</td></tr><tr><th>Maßnahme</th><td>{{ $d['corrective_action'] ?: '–' }}</td></tr><tr><th>Abschluss</th><td>{{ $d['resolved_at'] ?: 'offen' }}@if($d['resolved_by']) · {{ $d['resolved_by'] }}@endif</td></tr></table></div>@endforeach
@endif
</body></html>