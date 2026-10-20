<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>
@page { margin: 20mm 14mm; } body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #172741; }
h1 { font-size: 22px; } h2 { margin-top: 22px; font-size: 14px; } h3 { font-size: 11px; } h1, h2, h3 { page-break-after: avoid; } .detail-block { page-break-inside: avoid; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; } th, td { text-align: left; border-bottom: 1px solid #ddd; padding: 6px; } th { background: #eff2f6; } .money { text-align: right; white-space: nowrap; } .muted { color: #666; } tr { page-break-inside: avoid; } thead { display: table-header-group; }
</style></head><body>
@php
$money = fn($c) => number_format($c / 100, 2, ',', '.') . ' EUR';
$dateTime = fn($value) => \Carbon\Carbon::parse($value)->setTimezone('Europe/Berlin')->format('d.m.Y H:i');
$z = $snapshot['z_report']; $close = $snapshot['closing'];
$handoverEnvelopes = array_sum(array_map(fn($h) => $h['snapshot']['calculation']['envelope_cents'], $snapshot['handovers']));
@endphp
<h1>Hemingway · Tagesabschluss</h1>
<p>Geschäftstag {{ $snapshot['business_date'] }} · Abschluss TA-{{ $snapshot['day_id'] }} · Version {{ $snapshot['version'] }}</p>
<p class="muted">Z-Bericht {{ $z['number'] }} · {{ $z['register'] }}<br>Zeitraum {{ $dateTime($z['period_start']) }} bis {{ $dateTime($z['period_end']) }} Uhr<br>Originalbeleg: {{ $z['reference'] }} · Quelle: manuell erfasster Z-Bericht</p>
<h2>Umsatz ohne Trinkgeld</h2>
<table><thead><tr><th>Steuersatz</th><th class="money">Netto</th><th class="money">Steuer</th><th class="money">Brutto</th></tr></thead><tbody>
@foreach(['7', '19'] as $rate)<tr><td>{{ $rate }} %</td><td class="money">{{ $money($z['net'.$rate.'_cents']) }}</td><td class="money">{{ $money($z['tax'.$rate.'_cents']) }}</td><td class="money">{{ $money($z['gross'.$rate.'_cents']) }}</td></tr>@endforeach
<tr><th>Gesamt</th><th class="money">{{ $money($z['net7_cents'] + $z['net19_cents']) }}</th><th class="money">{{ $money($z['tax7_cents'] + $z['tax19_cents']) }}</th><th class="money">{{ $money($z['gross7_cents'] + $z['gross19_cents']) }}</th></tr></tbody></table>
<table><thead><tr><th>Zahlart</th><th class="money">Umsatz</th><th class="money">Soll laut Kasse</th></tr></thead><tbody>
<tr><td>Bar</td><td class="money">{{ $money($z['cash_sales_cents']) }}</td><td class="money">{{ $money($z['cash_due_cents']) }}</td></tr>
<tr><td>Karte</td><td class="money">{{ $money($z['card_sales_cents']) }}</td><td class="money">{{ $money($z['card_due_cents']) }}</td></tr></tbody></table>
<table><tbody><tr><td>Gutscheinverkauf (Mehrzweck), separat von den Steuerumsätzen</td><td class="money">{{ $money($z['voucher_sales_cents'] ?? 0) }}</td></tr></tbody></table>
<p class="muted">Gutscheinverkauf ist in den Zahlart-Sollbeträgen bereits enthalten und wird nicht nochmals zum Bargeld addiert. Es gilt die Tagessumme aus dem Z-Bericht, einschließlich bereits übergebener Schichten.</p>
<p class="muted">Kartentrinkgeld ist im Bar-Soll bereits berücksichtigt und wird nicht erneut abgezogen. {{ $z['reconciliation_note'] ?? '' }}</p>
<h2>Bargeld und Umschläge</h2>
<table><tbody>
<tr><td>Anfangsbestand des Geschäftstags</td><td class="money">{{ $money($snapshot['opening_cents']) }}</td></tr>
@foreach($snapshot['handovers'] as $handover)
<tr><td>Umschlag SU-{{ $handover['id'] }} · {{ $handover['snapshot']['label'] }}</td><td class="money">{{ $money($handover['snapshot']['calculation']['envelope_cents']) }}</td></tr>
@endforeach
<tr><td>Umschlag TA-{{ $snapshot['day_id'] }} beim Tagesabschluss</td><td class="money">{{ $money($close['envelope_cents']) }}</td></tr>
<tr><th>Umschläge gesamt</th><th class="money">{{ $money($handoverEnvelopes + $close['envelope_cents']) }}</th></tr>
<tr><td>Erwartet vor letzter Umschlagentnahme</td><td class="money">{{ $money($close['expected_cents']) }}</td></tr>
<tr><td>Gezählt vor letzter Umschlagentnahme</td><td class="money">{{ $money($close['counted_cents']) }}</td></tr>
<tr><td>Differenz beim Tagesabschluss</td><td class="money">{{ $money($close['difference_cents']) }}</td></tr>
<tr><th>Verbleibender tatsächlicher Bestand</th><th class="money">{{ $money($close['remaining_cents']) }}</th></tr>
<tr><td>Wechselgeld-Soll</td><td class="money">{{ $money($snapshot['float_target_cents']) }}</td></tr>
<tr><td>Auffüllbedarf</td><td class="money">{{ $money($close['top_up_cents']) }}</td></tr>
</tbody></table><p>{{ $close['note'] }}</p>
@php $sections = array_map(fn($h) => [...$h['snapshot'], 'label' => 'SU-'.$h['id'].' · '.$h['snapshot']['label']], $snapshot['handovers']); $sections[] = ['label' => 'Ergänzungen Tagesabschluss', ...$snapshot['supplement']]; @endphp
@foreach($sections as $section)
@if(!empty($section['reports']) || !empty($section['workers']) || !empty($section['movements']))
<div class="detail-block">
<h2>{{ $section['label'] }}</h2>
@if(isset($section['calculation']))<p>Differenz: {{ $money($section['calculation']['difference_cents']) }} · Restbestand: {{ $money($section['calculation']['remaining_cents']) }} · {{ $section['note'] }}</p>@endif
@if(!empty($section['reports']))<table><thead><tr><th>Bediener / Referenz</th><th class="money">Barumsatz</th><th class="money">Bar Soll</th><th class="money">Kartenumsatz</th><th class="money">Karte Soll</th></tr></thead><tbody>@foreach($section['reports'] as $report)<tr><td>{{ $report['operator'] }}<br>{{ $report['reference'] }}<br><span class="muted">Gutscheinverkauf: {{ $money($report['voucher_sales_cents'] ?? 0) }}</span></td>@foreach(['cash_sales_cents','cash_due_cents','card_sales_cents','card_due_cents'] as $key)<td class="money">{{ $money($report[$key]) }}</td>@endforeach</tr>@endforeach</tbody></table>@endif
</div>
@if(!empty($section['workers']))<div class="detail-block"><h3>Mitarbeiterstunden und Auszahlungen</h3><table><thead><tr><th>Mitarbeiter</th><th>Stunden</th><th class="money">Satz</th><th class="money">Betrag</th><th>Bar ausgezahlt</th></tr></thead><tbody>@foreach($section['workers'] as $worker)<tr><td>{{ $worker['name'] }}<br>{{ $worker['note'] }}</td><td>{{ number_format($worker['minutes']/60, 2, ',', '.') }}</td><td class="money">{{ $money($worker['hourly_rate_cents']) }}</td><td class="money">{{ $money($worker['amount_cents']) }}</td><td>{{ $worker['paid_cash'] ? 'Ja' : 'Nein' }}</td></tr>@endforeach</tbody></table></div>@endif
@if(!empty($section['movements']))<div class="detail-block"><h3>Geldbewegungen (Einlagen negativ dargestellt)</h3><table><thead><tr><th>Art</th><th>Beschreibung / Beleg</th><th class="money">Betrag</th></tr></thead><tbody>@foreach($section['movements'] as $movement)<tr><td>{{ ['purchase'=>'Einkauf','expense'=>'Ausgabe','deposit'=>'Einlage','withdrawal'=>'Entnahme'][$movement['kind']] }}</td><td>{{ $movement['description'] }}<br>{{ $movement['receipt'] }}</td><td class="money">{{ $money($movement['amount_cents']) }}</td></tr>@endforeach</tbody></table></div>@endif
@endif
@endforeach
<p class="muted">Abgeschlossen {{ $dateTime($snapshot['finalized_at']) }} Uhr · Benutzer {{ $snapshot['finalized_by'] }}<br>Ereignis-ID: {{ $snapshot['event_id'] }}</p>
</body></html>
