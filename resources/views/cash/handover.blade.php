<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><style>
@page { margin: 16mm 13mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #20251e; }
h1 { font-size: 21px; margin: 8px 0; } h2 { font-size: 13px; margin-top: 20px; page-break-after: avoid; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; table-layout: fixed; }
th, td { padding: 6px; border-bottom: 1px solid #d8d9cb; text-align: left; vertical-align: top; overflow-wrap: break-word; }
th { background: #e8efdf; } .money { text-align: right; } .muted { color: #586456; font-size: 9px; }
tr { page-break-inside: avoid; } thead { display: table-header-group; } .logo { float: right; width: 64px; height: 64px; }
</style></head><body>
@php
$money = fn($c) => number_format($c / 100, 2, ',', '.') . ' EUR';
$calc = $snapshot['calculation'];
@endphp
<img class="logo" src="{{ public_path('brand/hemingway-logo.png') }}" alt="Hemingway">
<h1>Hemingway · Schichtübergabe</h1>
<p>Geschäftstag {{ \Carbon\Carbon::parse($day->business_date)->format('d.m.Y') }}<br>
Umschlag SU-{{ $handover->id }} · {{ $snapshot['label'] }}</p>
<p class="muted" style="clear:both">Bestätigt {{ \Carbon\Carbon::parse($handover->created_at, config('app.timezone'))->setTimezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr · Benutzer {{ $handover->created_by }}</p>
<h2>Gemeinsamer Bargeldbestand</h2>
<table><thead><tr><th>Position</th><th class="money">Betrag</th></tr></thead><tbody>
@foreach(['opening_cents'=>'Bestand vor dieser Übergabe', 'cash_due_cents'=>'Bar Soll dieser Übergabe', 'movements_cents'=>'Ausgaben / Entnahmen abzüglich Einlagen', 'paid_cents'=>'Tatsächlich bar ausgezahlte Mitarbeiterbeträge', 'expected_cents'=>'Erwartet vor Umschlagentnahme', 'counted_cents'=>'Gezählt vor Umschlagentnahme', 'difference_cents'=>'Kassendifferenz', 'envelope_cents'=>'In Umschlag entnommen', 'remaining_cents'=>'Verbleibender tatsächlicher Bestand', 'float_target_cents'=>'Wechselgeld-Soll', 'top_up_cents'=>'Auffüllbedarf'] as $key=>$label)
<tr><td>{{ $label }}</td><td class="money">{{ $money($calc[$key]) }}</td></tr>
@endforeach
</tbody></table>
<p>{{ $snapshot['note'] }}</p>
<h2>Bediener-X-Berichte dieser Übergabe</h2>
<table><thead><tr><th>Bediener / Referenz</th><th class="money">Barumsatz</th><th class="money">Bar Soll</th><th class="money">Kartenumsatz</th><th class="money">Karte Soll</th></tr></thead><tbody>
@foreach($snapshot['reports'] as $report)<tr><td>{{ $report['operator'] }}<br>{{ $report['reference'] }}<br><span class="muted">Gutscheinverkauf: <span style="white-space:nowrap">{{ $money($report['voucher_sales_cents'] ?? 0) }}</span></span></td>@foreach(['cash_sales_cents','cash_due_cents','card_sales_cents','card_due_cents'] as $key)<td class="money">{{ $money($report[$key]) }}</td>@endforeach</tr>@endforeach
</tbody></table>
<p class="muted">Gutscheinverkauf (Mehrzweck) ist in den Zahlart-Sollbeträgen bereits enthalten und wird nicht nochmals zum Bargeld addiert.</p>
<p class="muted">Bar Soll enthält das bereits bar ausgezahlte Kartentrinkgeld. Es wird nicht nochmals abgezogen.</p>
@if(!empty($snapshot['workers']))
<h2>Mitarbeiterstunden und Auszahlungen</h2>
<table><thead><tr><th>Mitarbeiter</th><th>Stunden</th><th class="money">Stundensatz</th><th class="money">Betrag</th><th>Bar ausgezahlt</th></tr></thead><tbody>
@foreach($snapshot['workers'] as $worker)<tr><td>{{ $worker['name'] }}<br>{{ $worker['note'] }}</td><td>{{ number_format($worker['minutes']/60, 2, ',', '.') }}</td><td class="money">{{ $money($worker['hourly_rate_cents']) }}</td><td class="money">{{ $money($worker['amount_cents']) }}</td><td>{{ $worker['paid_cash'] ? 'Ja' : 'Nein' }}</td></tr>@endforeach
</tbody></table>
@endif
@if(!empty($snapshot['movements']))
<h2>Geldbewegungen</h2>
<table><thead><tr><th>Art</th><th>Beschreibung / Beleg</th><th class="money">Betrag</th></tr></thead><tbody>
@foreach($snapshot['movements'] as $movement)<tr><td>{{ ['purchase'=>'Einkauf','expense'=>'Ausgabe','deposit'=>'Einlage','withdrawal'=>'Entnahme'][$movement['kind']] }}</td><td>{{ $movement['description'] }}<br>{{ $movement['receipt'] }}</td><td class="money">{{ $money($movement['amount_cents']) }}</td></tr>@endforeach
</tbody></table>
<p class="muted">Einlagen sind als negative Geldbewegung dargestellt.</p>
@endif
<p class="muted">Mitarbeiterstunden dieser Abrechnung sind unabhängig von Zeiterfassung und Lohnexport.</p>
</body></html>
