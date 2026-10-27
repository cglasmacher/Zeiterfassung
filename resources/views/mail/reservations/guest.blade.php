<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:24px 12px;background:#f3f4ee;color:#34442c;font-family:Arial,sans-serif;line-height:1.6;">
<table role="presentation" style="width:100%;max-width:580px;margin:0 auto;border-collapse:collapse;"><tr><td style="padding:28px;background:#fff;border-radius:16px;">
<p style="margin:0;font-family:Georgia,serif;font-size:32px;color:#758951;">Hemingway</p><p style="margin:0 0 24px;color:#687362;font-size:13px;">Café · Bar · Restaurant · Hilden</p>
<h1 style="font-size:23px;line-height:1.3;">{{ $heading }}</h1>
<p>Hallo {{ $details['guest_name'] }},</p>
@if($type === 'reminder')
<p>Wir freuen uns auf deinen Besuch! Hier noch einmal die Angaben zu deiner bestätigten Reservierung:</p>
@elseif(in_array($type, ['waiting', 'waiting_updated']))
<p>Wir haben deinen Wartelisteneintrag {{ $type === 'waiting_updated' ? 'geändert' : 'erhalten' }}. <strong>Das ist noch keine bestätigte Reservierung.</strong> Sobald wir dir einen Tisch verbindlich anbieten können, erhältst du eine separate Bestätigung.</p>
@elseif($type === 'requested')
<p>Vielen Dank für deine Anfrage. Wir prüfen sie und melden uns bei dir. <strong>Dies ist noch keine bestätigte Reservierung.</strong></p>
@elseif($type === 'waiting_cancelled')
<p>Dein Wartelisteneintrag wurde storniert. Du stehst für diesen Termin nicht mehr auf der Warteliste.</p>
@elseif($type === 'cancelled')
<p>Deine Reservierung wurde storniert. Für diesen Termin ist kein Tisch mehr für dich reserviert.</p>
@elseif($type === 'updated')
<p>Deine bestätigte Reservierung wurde geändert. Es gelten jetzt die folgenden Angaben:</p>
@else
<p>Deine Reservierung ist verbindlich bestätigt. Wir freuen uns auf deinen Besuch!</p>
@endif
<table role="presentation" style="width:100%;margin:24px 0;background:#f1f4eb;border-radius:10px;padding:16px;"><tr><td>Datum</td><td style="text-align:right;"><strong>{{ $details['date'] }}</strong></td></tr><tr><td>Uhrzeit</td><td style="text-align:right;">{{ $details['time'] }} Uhr</td></tr><tr><td>Gäste</td><td style="text-align:right;">{{ $details['party_size'] }}</td></tr><tr><td>Buchungsnummer</td><td style="text-align:right;">{{ $details['reference'] }}</td></tr></table>
@if($cancel_url ?? null)
<p style="margin:24px 0;"><a href="{{ $cancel_url }}" style="display:inline-block;padding:12px 18px;border:1px solid #758951;border-radius:8px;color:#455b31;text-decoration:none;">{{ in_array($type, ['waiting', 'waiting_updated']) ? 'Wartelisteneintrag stornieren' : 'Termin stornieren' }}</a></p><p style="font-size:13px;color:#687362;">Der persönliche Link führt zu einer Bestätigungsseite. Online-Stornierung ist bis zum Reservierungsbeginn möglich. Bitte leite den Link nicht weiter.</p>
@endif
<p>Für Rückfragen, Änderungen oder eine Absage antworte bitte auf diese E-Mail und nenne deine Buchungsnummer.</p>
<p>Dein Hemingway-Team</p><hr style="border:0;border-top:1px solid #e5e9dd;margin:24px 0;"><p style="font-size:13px;color:#687362;">Hemingway Gastro &amp; Events GmbH<br>Markt 16 · 40721 Hilden</p>
</td></tr></table></body></html>
