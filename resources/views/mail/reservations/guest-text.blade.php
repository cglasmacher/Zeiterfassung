Hemingway · Hilden
{{ $heading }}

Hallo {!! $details['guest_name'] !!},

@if(in_array($type, ['waiting', 'waiting_updated']))
Wir haben deinen Wartelisteneintrag {{ $type === 'waiting_updated' ? 'geändert' : 'erhalten' }}. Das ist noch keine bestätigte Reservierung. Sobald wir dir einen Tisch verbindlich anbieten können, erhältst du eine separate Bestätigung.
@elseif($type === 'requested')
Vielen Dank für deine Anfrage. Wir prüfen sie und melden uns bei dir. Dies ist noch keine bestätigte Reservierung.
@elseif($type === 'waiting_cancelled')
Dein Wartelisteneintrag wurde storniert. Du stehst für diesen Termin nicht mehr auf der Warteliste.
@elseif($type === 'cancelled')
Deine Reservierung wurde storniert. Für diesen Termin ist kein Tisch mehr für dich reserviert.
@elseif($type === 'updated')
Deine bestätigte Reservierung wurde geändert. Es gelten jetzt die folgenden Angaben:
@else
Deine Reservierung ist verbindlich bestätigt. Wir freuen uns auf deinen Besuch!
@endif

Datum: {{ $details['date'] }}
Uhrzeit: {{ $details['time'] }} Uhr
Gäste: {{ $details['party_size'] }}
Buchungsnummer: {{ $details['reference'] }}

Für Rückfragen, Änderungen oder eine Absage antworte bitte auf diese E-Mail und nenne deine Buchungsnummer.

Dein Hemingway-Team
Hemingway Gastro & Events GmbH
Markt 16 · 40721 Hilden
