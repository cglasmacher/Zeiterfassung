# Hemingway Reservierungen

## Tischplan-Builder – erster Baustein

Aufruf als Administrator: **Betrieb → Tischplan-Builder** (`/reservierungen/tischplan`). Der Builder ist in die bestehende Weboberfläche und damit auch in die App eingebunden. Eine separate iPad-App ist nicht vorgesehen.

Nach dem Deployment:

```bash
php artisan migrate --force
npm install
npm run build
php artisan optimize:clear
```

### Bedienung

1. „Raum anlegen“ wählen. Ein leerer Raum oder die Startvorlagen „Restaurant“ und „Theke“ stehen bereit.
2. Die Restaurantvorlage enthält Tische 1–8 und 11–17 mit jeweils zwei Plätzen. Die Thekenvorlage enthält Tisch 21 mit vier und Tisch 20 mit acht Plätzen. Ihre Anordnung ist vorläufig, kein rekonstruierter Grundriss.
3. Tische und Wände, Türen, Theke, Pflanzen oder Texte hinzufügen. Objekte im Plan oder Tische in der Liste auswählen.
4. Objekte per Maus oder Touch verschieben. Name, Plätze, Form, Größe, Position und Drehung rechts bearbeiten. Die Position bezeichnet den Objektmittelpunkt. Maße sind Planeinheiten, keine verbindlichen Zentimeterangaben.
5. Optional am Raster ausrichten; mit Zoom und Scrollen größere Pläne bearbeiten. Die Pfeiltasten bewegen ein ausgewähltes Objekt, wenn die Zeichenfläche den Fokus hat. Umschalt bewegt in Zehnerschritten.
6. Rückgängig/Wiederholen oder Strg/⌘ Z verwenden. Duplikate erhalten neue Kennungen und Tischnamen.
7. „Änderungen speichern“ speichert den gesamten ausgewählten Raum. Raumwechsel und Verlassen der Seite warnen vor ungespeicherten Änderungen. Das Anlegen eines Raums selbst wird sofort gespeichert.

### Speicherung und Schutz

- Räume und Tische besitzen dauerhafte UUIDs. Layoutänderungen ersetzen Tischkennungen nicht.
- Entfernte Tische werden weich gelöscht; erneutes Anlegen derselben Kennung ist nicht möglich. Nach dem Speichern beginnt die Rückgängig-Historie neu.
- Änderungen am Raum erfolgen in einer Datenbanktransaktion. Revision und Zeilensperre verhindern das Überschreiben durch einen veralteten Editor. Bei HTTP 409 bleibt der lokale Entwurf erhalten; ein explizites Neuladen verwirft ihn nach Bestätigung.
- Serverseitige Validierung prüft unter anderem eindeutige Tischnamen je Raum, Kapazitäten, fremde Tischkennungen, Objekttypen und Raumgrenzen einschließlich gedrehter Objekte.
- Räumliche Überschneidungen sind im Gestaltungsmodus erlaubt. Sie stellen keine tatsächlichen Reservierungen dar.
- Alle Seiten und API-Endpunkte sind nur für Administratoren zugänglich. Bestehende Teamrechte werden nicht erweitert.
- Keine automatische Veränderung von Resmio, Online-Buchungen oder Küchen-TV.

## Reservierungsbuch – interner Betrieb

Aufruf unter **Betrieb → Reservierungen** (`/reservierungen`). Administratoren und die bestehende Rolle `operations` können den Service bedienen. Builder und Regelverwaltung bleiben Administratoren vorbehalten.

- Tagesübersicht mit Datumsauswahl, Suche, Filtern für Reservierungen/Walk-ins/Warteliste, Gastzahlen und Aktualisierung alle 30 Sekunden.
- Reservierungen mit Kontaktdaten, Notizen, Aufenthaltsdauer und automatischer oder manueller Tischwahl. Vorgeschlagene Optionen enthalten ausschließlich freie Einzelplätze als ganze Tische sowie ausdrücklich hinterlegte Kombinationen; fremde Gruppen teilen sich keinen Tisch.
- Walk-ins benötigen keine Kontaktdaten und starten mit der aktuellen Uhrzeit. Auch außerhalb der regulären Reservierungszeiten können sie platziert werden.
- Wartelisteneinträge halten keine Kapazität frei. „Übernehmen“ prüft erneut und macht daraus entweder eine bestätigte Reservierung oder einen jetzt platzierten Walk-in. Ein Wartelisteneintrag kann nicht durch eine reine Statusänderung platziert werden.
- Status: unbestätigt, bestätigt, angekommen, platziert, abgeschlossen, storniert, No-Show. Unbestätigte Buchungen halten bereits einen Tisch frei. Abgeschlossene/stornierte/No-Show-Buchungen blockieren keinen Platz.
- „Tisch freigeben“ beendet die Belegung. Wenn platzierte Gäste über die geplante Aufenthaltsdauer hinaus bleiben, wird der Tisch bis zur Freigabe weiterhin blockiert – auch für zukünftige Buchungsanfragen. Das verhindert, dass eine überfällige Belegung stillschweigend als frei gilt.
- Tischübersicht mit Raum-/Zeitwahl auf Grundlage des Builders: frei, reserviert, platziert oder gesperrt. Bei Kombinationen ist die Gesamtgästezahl mit „ges.“ markiert. Buchungen lassen sich durch Anklicken bearbeiten.
- Einmalige Sperren für einen frei wählbaren Zeitraum: gesamtes Restaurant, Raum oder einzelne Tische. Bestehende aktive Buchungen werden nicht verdrängt; vor der Sperre müssen sie umgesetzt/storniert werden. Wartelisteneinträge bleiben möglich.
- Bearbeitung erfolgt mit Buchungsversion. Ein veralteter Dialog kann eine zwischenzeitliche Änderung nicht überschreiben. Buchungsänderungen werden mit Bearbeiter protokolliert.

### Regeln und Kombinationen

**Betrieb → Reservierungsregeln** (`/reservierungen/verwaltung`) verwaltet die geöffneten Wochentage, einen täglichen Zeitraum für Reservierungsbeginn, den Standardaufenthalt, gruppenabhängige Dauer sowie Pflichtfelder. Der gespeicherte Buchungsintervall ist für spätere Online-Slots vorbereitet; interne Einträge dürfen minutengenau erfasst werden. Die Aufenthaltsdauer kann über den letzten möglichen Reservierungsbeginn hinausreichen. Mehrere unterschiedliche Tagesfenster, Feiertagsöffnungen und wiederkehrende Sperren folgen später.

Tischkombinationen werden mit eigener gemeinsamer Kapazität angelegt. „Hemingway-Kombinationen übernehmen“ übernimmt die elf aus den Screenshots dokumentierten Gruppen für den gewählten Raum anhand der vorhandenen Tischnamen. Wiederholtes Übernehmen erzeugt keine Duplikate. Fehlende Tischgruppen werden übersprungen. Eine aktuell/für die Zukunft verwendete Kombination darf nicht entfernt werden. Änderungen erfolgen derzeit durch Entfernen und erneutes Anlegen, sobald sie nicht mehr verwendet wird.

### Verfügbarkeit und Builder-Schutz

Die Belegung verwendet halboffene Zeitintervalle: Ein Tisch bis 13:00 kann ab 13:00 erneut gebucht werden. Bei jedem Speichern und bei der Wartelistenübernahme wird die ganze Aufenthaltsdauer geprüft. Jeder beteiligte Tisch einer Kombination wird exklusiv blockiert.

Alle kapazitätsrelevanten Änderungen verwenden innerhalb einer Datenbanktransaktion dieselbe Sperre auf der dauerhaft vorhandenen Einstellungszeile, bevor sie die Verfügbarkeit prüfen. Auch Builder-Änderungen verwenden diese Sperre. Die Tabellen behalten UUIDs; gebuchte Tische können nicht entfernt oder verkleinert werden. Zum Entfernen eines Tisches müssen zunächst seine Kombinationen entfernt werden. Dekorative Layoutänderungen bleiben möglich.

Buchungs- und Sperrzeiten werden explizit in UTC gespeichert und in Europe/Berlin angezeigt; dies hängt nicht von der Browser-Zeitzone ab. Nicht existierende Uhrzeiten beim Wechsel auf Sommerzeit werden abgelehnt. Die doppelte Stunde bei der Rückstellung wird nach der PHP-Zeitzonenauflösung verarbeitet; eine separate Auswahl des ersten/zweiten Auftretens ist noch nicht vorgesehen.

## Vereinbarte weitere Entwicklung

Zielumfang: funktionale Orientierung an Resmio Ultimate samt Add-ons; die Funktionen der iPad-App werden in die Gesamtlösung integriert. Builder und interner Reservierungskern sind umgesetzt. Der weitere Ultimate-Umfang bleibt als Ziel bestehen.

Geplante Bausteine:

1. Ausbau der Regeln um Ausnahmen/Feiertage, wiederkehrende Sperren und weitere Kapazitätsgrenzen.
2. Wochen-/Agenda-/Zeitstrahlansicht, Serienreservierungen und weitere Komfortfunktionen für den vorhandenen Tagesbetrieb.
3. Online-Widget, Gastverwaltung, Bestätigungen, Erinnerungen, Nachrichten und Stornierungen.
4. Anzahlungen, No-Show-Abwicklung, Automatisierungen, Auswertungen und bestehende Dienstplan-/TV-Anbindung.
5. Marketing, Feedback, Erweiterung bestehender Gutscheine/Tickets sowie weitere vereinbarte Module. Externe Buchungskanäle, SMS, Telefonassistent und Zahlungsdienste benötigen eigene Integrationen.

Die öffentliche Online-Buchung, Gästemails, Resmio-Import/Umschaltung und Küchen-TV werden durch diesen Ausbau noch nicht aktiviert. Der interne Reservierungskern läuft zunächst getrennt von den bisherigen Resmio-Buchungen. Vor einem produktiven Parallelbetrieb müssen bestehende Reservierungen vollständig übernommen werden; die internen Buchungen kennen Resmio-Belegungen derzeit nicht.

### Aus den Screenshots übernommene Werte

- Noch für Online-Ausbau vorgesehen: automatische Bestätigung bis 8 Personen; online 1–8 Personen. Interne Reservierungen werden jetzt bestätigt angelegt.
- Mo und Mi–So 10:00–20:00; 30-Minuten-Intervall, insgesamt 30 Minuten Vorlauf, 365 Tage Buchungshorizont.
- Aufenthaltsdauer: Standard 90, ab 2 Personen 105, ab 4 Personen 135, ab 6 Personen 150 Minuten.
- Name und Telefon bei manuellen Buchungen verpflichtend; E-Mail optional.
- Erinnerungen, Antwortfunktion, Online-Warteliste und Raumauswahl einschließlich „Beliebiger Raum“ aktiv; PIN-Prüfung aus.
- Automatische Tischzuordnung und individuelle Kombinationen aktiv; manuelle Überbuchung und gemeinsame Online-Belegung durch fremde Gruppen aus.
- Tischkombinationen/Plätze: 1+3/5, 1+3+4/7, 6+7/5, 4+6+7/7, 11+12+13/8, 17+15+16/7, 11+12/5, 17+16/5, 13+14/4, 11+12+13+14/9, 1+2+3/7.
- Interne Tagesfilter: Frühstück 10:00–11:55, Mittagstisch 12:00–16:55, Abendessen 17:00–23:55. TV-Zeitblöcke separat.
- Feiertagsausnahmen, aktivierte Reservierungs-Tags und differenzierte Mailvorlagen aus den bereitgestellten Screenshots berücksichtigen. Die Datumsbereichs-Semantik der Silvester-Ausnahme ist vor Übernahme zu klären.

### Prüfung

```bash
php artisan test --filter='ReservationBookTest|FloorPlanTest'
node --test tests/Workflow/floor-plan.test.mjs tests/Workflow/reservation-ui.test.mjs
npm run build
```

Die Featuretests verwenden die Benutzer-/Berechtigungs-, Builder- und Buchungs-Migrationen samt benötigten Abhängigkeiten isoliert. Eine bestehende HACCP-Migration verwendet MySQL-spezifisches `SHOW INDEX` und blockiert ansonsten den SQLite-Testlauf unabhängig vom Builder. Die Gesamtanwendung wird damit nicht als vollständig getestet ausgewiesen.
