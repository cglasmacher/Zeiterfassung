<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Reservierung stornieren · Hemingway</title>
<style>body{margin:0;background:#f3f4ee;color:#34442c;font:16px/1.6 system-ui,sans-serif;padding:40px 16px}main{box-sizing:border-box;max-width:560px;margin:auto;background:#fff;border-radius:20px;padding:32px}header{font:32px Georgia,serif;color:#758951}h1{font-size:25px;line-height:1.3}dl{background:#f1f4eb;padding:18px;border-radius:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px}dd{margin:0;text-align:right}button{width:100%;padding:15px;border:0;border-radius:10px;background:#9f3d37;color:#fff;font:600 16px system-ui;cursor:pointer}label{display:flex;gap:10px;align-items:flex-start;margin:24px 0}input{margin-top:6px;accent-color:#758951}footer{margin-top:28px;color:#687362;font-size:13px}p{overflow-wrap:anywhere}button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid #758951;outline-offset:3px}</style></head>
<body><main><header>Hemingway</header>
@if($state === 'confirm')
<h1>{{ $booking->kind === 'waitlist' ? 'Wartelisteneintrag stornieren?' : 'Reservierung stornieren?' }}</h1><p>Bitte prüfe den Termin. Erst mit deiner Bestätigung wird {{ $booking->kind === 'waitlist' ? 'der Wartelisteneintrag' : 'die Reservierung' }} storniert.</p>
@elseif($state === 'cancelled')
<h1>{{ $booking->kind === 'waitlist' ? 'Wartelisteneintrag storniert' : 'Reservierung storniert' }}</h1><p>Die Stornierung ist erfasst. {{ $booking->kind === 'waitlist' ? 'Du stehst für diesen Termin nicht mehr auf der Warteliste.' : 'Für diesen Termin ist kein Tisch mehr für dich reserviert.' }}</p>
@elseif($state === 'invalid')
<h1>Dieser Link ist nicht mehr gültig</h1><p>Nutze bitte den Link aus deiner neuesten Buchungsmail. Nach Änderungen an Termin, Gästezahl oder E-Mail-Adresse wird ein neuer Link benötigt. Ab Reservierungsbeginn ist eine Online-Stornierung nicht mehr möglich. Bei Fragen antworte bitte auf deine Buchungsmail.</p>
@else
<h1>Online-Stornierung nicht mehr möglich</h1><p>Diese Buchung kann online nicht mehr storniert werden. Bitte wende dich direkt an das Hemingway-Team oder antworte auf deine Buchungsmail.</p>
@endif
@if($booking)
<dl><dt>Datum</dt><dd>{{ $booking->starts_at->format('d.m.Y') }}</dd><dt>Uhrzeit</dt><dd>{{ $booking->starts_at->format('H:i') }} Uhr</dd><dt>Gäste</dt><dd>{{ $booking->party_size }}</dd><dt>Buchungsnummer</dt><dd>{{ $booking->public_reference }}</dd></dl>
@endif
@if($state === 'confirm')
<form method="post" action="{{ $action }}">@csrf
<label><input type="checkbox" name="confirm" value="1" required><span>Ja, ich möchte {{ $booking->kind === 'waitlist' ? 'diesen Wartelisteneintrag' : 'diese Reservierung' }} stornieren.</span></label><button type="submit">Verbindlich stornieren</button></form><p>Du möchtest den Termin behalten? Schließe diese Seite einfach.</p>
@endif
<footer>Hemingway · Markt 16 · 40721 Hilden</footer></main></body></html>
