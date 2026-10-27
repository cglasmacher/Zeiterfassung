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

- Kompakte Tagesliste mit Spalten für Typ, Zeit, Gast, Personen, Tische, Notizen und Status. Tags stehen beim Gast, lange Notizen bleiben als zweizeilige Vorschau sichtbar und lassen sich mitsamt getrennten Gast-/Resmio-Notizen und Prüfhinweisen aufklappen. Datumsauswahl, Suche, Filter für Reservierungen/Walk-ins/Warteliste, Gastzahlen und Aktualisierung alle 30 Sekunden bleiben erhalten. Die Zeitspalte lässt sich auf- und absteigend sortieren. Das Statusmenü bietet die bestehenden Serviceaktionen; Gastname, Uhrzeit, Tischangabe oder Stiftsymbol öffnen die Bearbeitung. Lokale Wartelisteneinträge lassen sich direkt übernehmen. Auf schmalen Bildschirmen werden dieselben Informationen ohne seitlichen Seitenüberlauf untereinander angeordnet.
- Reservierungen mit Kontaktdaten, Notizen, Aufenthaltsdauer und automatischer oder manueller Tischwahl. Die Kachelauswahl zeigt einzelne Tische mit ihrer Kapazität, Raumfilter, „Verfügbar/Alle“, Sortierung und Mehrfachauswahl. Ausgewählte Tische sind dunkel hervorgehoben; Plätze und zusätzlich benötigte Stühle werden laufend gezählt. Eine zu kleine konfigurierte Kapazität ist bei expliziter manueller Tischwahl ein Hinweis und verhindert das Speichern nicht; zum Beispiel sind 7 Gäste an drei Zweiertischen mit einem zusätzlichen Kopfstuhl möglich. Das gilt für Anlage und Bearbeitung sowie für lokale und importierte Buchungen. Die hinterlegten Tischkapazitäten werden dabei nicht verändert. Unter „Alle“ bleiben belegte und gesperrte Tische mit Grund sichtbar und sind nicht auswählbar. Manuell können beliebige freie Tische desselben Raums gemeinsam gewählt werden; für eine exakt hinterlegte Kombination gilt deren gemeinsame Kapazität, sonst die Summe der Tischplätze. Fremde Gruppen teilen sich keinen Tisch. „Automatisch zuordnen“ verwendet weiterhin einen passenden Einzeltisch oder eine ausdrücklich hinterlegte Kombination.
- Walk-ins benötigen keine Kontaktdaten und starten mit der aktuellen Uhrzeit. Auch außerhalb der regulären Reservierungszeiten können sie platziert werden.
- Wartelisteneinträge halten keine Kapazität frei. „Übernehmen“ prüft erneut und macht daraus entweder eine bestätigte Reservierung oder einen jetzt platzierten Walk-in. Ein Wartelisteneintrag kann nicht durch eine reine Statusänderung platziert werden.
- Status: **Angefragt, Bestätigt, Eingetroffen, Fertig, Storno, No-Show**. „Eingetroffen“ fasst die bisherigen Zustände „angekommen“ und „platziert“ zusammen und färbt belegte Tische rot. Angefragte Buchungen halten bereits einen Tisch frei; Fertig/Storno/No-Show geben die Belegung frei. Wartelisteneinträge behalten ihren gesonderten Zustand „Wartet“. Intern wird der gemeinsame Ankunftszustand als `seated` gespeichert; ältere Daten und API-Eingaben mit `arrived` werden kompatibel als derselbe Zustand gelesen bzw. geschrieben. Es ist keine Datenmigration erforderlich.
- „Fertig“ beendet die Belegung vorzeitig. Die Verfügbarkeit und die Tischübersicht prüfen für alle aktiven Status einschließlich „Eingetroffen“ ausschließlich den gespeicherten Zeitraum von Beginn bis Ende. Eine Buchung bis 20:00 erlaubt eine Folgebuchung ab 20:00. Ein überfälliger Status blockiert weder spätere Uhrzeiten noch nachfolgende Tage unbegrenzt; bei längerem Aufenthalt muss die Aufenthaltsdauer angepasst werden.
- Tischübersicht mit Raum-/Zeitwahl auf Grundlage des Builders: frei (grün), angefragt (gelb), bestätigt (blau), eingetroffen (rot), fertig oder gesperrt (grau). Die Tischfarbe gilt für den ausgewählten Zeitpunkt; bei überlappenden Importen hat eine eingetroffene Gruppe Vorrang. Unmittelbar links oder rechts jedes Tisches stehen nur die Startuhrzeiten der Tagesbuchungen, klein, horizontal, chronologisch und nach Status eingefärbt. Es gibt keine Verbindungslinien und keinen entfernten Sammelbereich für Uhrzeiten. Die Seite und kleine vertikale Versätze werden anhand der benachbarten Tische und Markierungen gewählt. Enge Grundrisse bleiben scrollbar, an Außenkanten liegende Markierungen werden nicht abgeschnitten. Fertige Zeiten bleiben grau sichtbar, Storno, No-Show und Wartelistenzeiten werden weggelassen. Mehrere Buchungen und Buchungen über Mitternacht werden berücksichtigt. Bei Kombinationen stehen die Startzeiten an jedem beteiligten Tisch; die Gesamtgästezahl ist mit „ges.“ markiert. Tisch oder Zeitmarkierung anklicken zum Bearbeiten; die gesamte Aufenthaltsdauer bleibt in der Buchung erhalten und wird weiterhin bei der Verfügbarkeit geprüft.

- Einmalige Sperren für einen frei wählbaren Zeitraum: gesamtes Restaurant, Raum oder einzelne Tische. Bestehende aktive Buchungen werden nicht verdrängt; vor der Sperre müssen sie umgesetzt/storniert werden. Wartelisteneinträge bleiben möglich.
- Bearbeitung erfolgt mit Buchungsversion. Ein veralteter Dialog kann eine zwischenzeitliche Änderung nicht überschreiben. Buchungsänderungen werden mit Bearbeiter protokolliert.

### Regeln und Kombinationen

**Betrieb → Reservierungsregeln** (`/reservierungen/verwaltung`) verwaltet die geöffneten Wochentage, einen täglichen Zeitraum für Reservierungsbeginn, den Standardaufenthalt, gruppenabhängige Dauer sowie Pflichtfelder. Der gespeicherte Buchungsintervall ist für spätere Online-Slots vorbereitet; interne Einträge dürfen minutengenau erfasst werden. Die Aufenthaltsdauer kann über den letzten möglichen Reservierungsbeginn hinausreichen. Mehrere unterschiedliche Tagesfenster, Feiertagsöffnungen und wiederkehrende Sperren folgen später.

Tischkombinationen werden mit eigener gemeinsamer Kapazität angelegt. „Hemingway-Kombinationen übernehmen“ übernimmt die elf aus den Screenshots dokumentierten Gruppen für den gewählten Raum anhand der vorhandenen Tischnamen. Wiederholtes Übernehmen erzeugt keine Duplikate. Fehlende Tischgruppen werden übersprungen. Eine aktuell/für die Zukunft verwendete Kombination darf nicht entfernt werden. Änderungen erfolgen derzeit durch Entfernen und erneutes Anlegen, sobald sie nicht mehr verwendet wird.

### Verfügbarkeit und Builder-Schutz

Die Belegung verwendet halboffene Zeitintervalle: Ein Tisch bis 13:00 kann ab 13:00 erneut gebucht werden. Bei jedem Speichern und bei der Wartelistenübernahme wird die ganze Aufenthaltsdauer geprüft. Jeder beteiligte Tisch einer Kombination oder manuellen Mehrfachauswahl wird exklusiv blockiert. Die Verfügbarkeitsantwort enthält auch kleine Einzeltische, die erst zusammen ausreichend Plätze bieten. Die serverseitige Prüfung verwirft überlappende, gesperrte, entfernte und raumübergreifende Auswahlen atomar; sie vertraut nicht auf den vorher geladenen Kachelstatus.

Alle kapazitätsrelevanten Änderungen verwenden innerhalb einer Datenbanktransaktion dieselbe Sperre auf der dauerhaft vorhandenen Einstellungszeile, bevor sie die Verfügbarkeit prüfen. Auch Builder-Änderungen verwenden diese Sperre. Die Tabellen behalten UUIDs; gebuchte Tische können nicht entfernt oder verkleinert werden. Zum Entfernen eines Tisches müssen zunächst seine Kombinationen entfernt werden. Dekorative Layoutänderungen bleiben möglich.

Buchungs- und Sperrzeiten werden explizit in UTC gespeichert und in Europe/Berlin angezeigt; dies hängt nicht von der Browser-Zeitzone ab. Nicht existierende Uhrzeiten beim Wechsel auf Sommerzeit werden abgelehnt. Die doppelte Stunde bei der Rückstellung wird nach der PHP-Zeitzonenauflösung verarbeitet; eine separate Auswahl des ersten/zweiten Auftretens ist noch nicht vorgesehen.

## Resmio-Abgleich in das Reservierungsbuch

Der vorhandene, verschlüsselt gespeicherte API-Zugang aus dem Infosystem wird wiederverwendet. Die Übernahme erfolgt ausschließlich **von Resmio nach Hemingway**; es werden keine Buchungen, Notizen, Tags oder Statusänderungen an Resmio gesendet. Der bestehende TV-Abgleich bleibt erhalten.

### Inbetriebnahme

Nach Merge und Deployment die Migrationen und den Frontend-Build wie oben ausführen. Anschließend als Administrator im Reservierungsbuch **Resmio synchronisieren** wählen oder auf dem Server starten:

```bash
php artisan info:sync-resmio
```

Der vorhandene Scheduler führt denselben Abgleich alle fünf Minuten aus. Dafür muss Laravel `schedule:run` wie bisher jede Minute laufen. Webhooks lösen zusätzlich einen Job aus; für deren unmittelbare Verarbeitung muss der bestehende Queue-Worker laufen. Nach Deployment lang laufende Worker mit `php artisan queue:restart` neu starten.

Standardfenster: gestern 00:00 bis zum Beginn des Tages 366 Tage in der Zukunft, jeweils Europe/Berlin. Optional lässt sich `RESMIO_SYNC_DAYS` auf 1–730 Tage setzen (danach Konfigurationscache erneuern). Es erfolgt kein vollständiger historischer Import. Die API wird paginiert gelesen; ein Fehler bei Buchungen, Tischen oder benötigten Tag-Verzeichnissen bricht die Übernahme vor der Datenänderung ab.

### Übernommene Angaben

- Dauerhafte Zuordnung über Facility und Resmio-Buchungs-ID; wiederholter Abgleich erzeugt keine Duplikate. Manuell angelegte Buchungen werden nicht anhand von Name oder E-Mail zusammengeführt.
- Datum, Personen, Aufenthaltsdauer, Kontakt, Status, Walk-in-Kennung, Quelle und Referenz; bei fehlender Aufenthaltsdauer gelten die lokalen Dauerregeln.
- Buchungsnotizen, Gastkommentare und dauerhafte Gästenotizen als getrennte Angaben.
- Reservierungs-Tags und Gäste-Tags mit aufgelösten Namen sowie zusätzliche Reservierungsformular-Angaben. Nicht benötigte Geheimnisse aus der API-Antwort werden nicht gespeichert.
- Tischzuordnung einschließlich mehrerer Tische. Liefert der Buchungs-Endpunkt den Status `waiting`, wird dieser als Wartelisteneintrag übernommen; ein gesonderter Resmio-Wartelisten-Endpunkt wird nicht abgefragt.

### Tischzuordnung und Konflikte

Unter **Reservierungsregeln → Resmio-Tischzuordnung** können Resmio-Tische einem vorhandenen lokalen Tisch zugewiesen werden. Beim ersten Einlesen erfolgt eine automatische Zuordnung nur, wenn der getrimmte Tischname (ohne Beachtung der Groß-/Kleinschreibung) im gesamten lokalen System eindeutig ist. Manuelle Zuordnungen bleiben bestehen und aktualisieren die betroffenen importierten Buchungen unmittelbar.

Aktive importierte Buchungen ohne vollständige Zuordnung blockieren für ihren Zeitraum vorsichtshalber alle lokalen Tischoptionen. Nach einer Zuordnung wird diese globale Sperre durch die tatsächliche Tischbelegung ersetzt. Überlappungen mit vorhandenen Buchungen, lokalen Sperren oder zu geringer Tischkapazität werden sichtbar markiert; die Quellbuchung wird dabei übernommen und nicht stillschweigend verdrängt. Diese Hinweise und die Tischzuordnung vor dem lokalen Weiterbuchen prüfen.

### Lokale Ergänzungen und Änderungen in Resmio

Die Stammdaten importierter Buchungen werden in Resmio gepflegt. In Hemingway können eigene interne Notizen und eine lokale Tischzuordnung ergänzt sowie die sechs lokalen Reservierungsstatus verwendet werden. Auch lokale Storno- und No-Show-Markierungen sind möglich; sie werden nicht an Resmio gesendet. Eine in Resmio bereits abschließend beendete Buchung kann lokal nicht wieder aktiviert werden. Beim Wiederherstellen eines lokalen Endstatus oder Zurücksetzen auf einen aktiven Resmio-Status wird die aktuelle Tischbelegung erneut geprüft. Sie bleiben beim nächsten Abgleich erhalten; der Resmio-Status bleibt separat sichtbar. „Wieder Resmio-Status übernehmen“ entfernt den lokalen Status. Eine Terminverschiebung oder ein abschließender Quellstatus hebt den lokalen Status ebenfalls auf. Die Tischwahl ist direkt im Bearbeitungsdialog für aktive Reservierungen und Walk-ins möglich: freie Tische per Kachel-Mehrfachauswahl; zusätzliche Stühle bleiben ein Hinweis und verhindern manuelles Speichern nicht. Es ist keine vorher angelegte Kombination nötig; eine exakt hinterlegte Kombination verwendet ihre eigene Kapazität. Die Auswahl wird beim Speichern unter derselben Datenbanksperre erneut für die komplette Aufenthaltsdauer geprüft. Lokale Tischänderungen überschreiben die Resmio-Ressourcen und die globale Ressourcen-Zuordnung nicht und werden nicht an Resmio gesendet. „Wieder Resmio-Tische übernehmen“ setzt eine verfügbare ursprüngliche Zuordnung zurück. Bei geänderter Quell-Startzeit oder Gästezahl wird die lokale Tischwahl zurückgesetzt und muss gegebenenfalls neu geprüft werden. Mehrere gleichzeitig unzugeordnete importierte Buchungen lassen sich nacheinander zuordnen; die vorsorgliche globale Kapazitätssperre bleibt für neue lokale Buchungen bestehen, solange weitere unzugeordnete Belegungen überlappen.

Neuere Quellstände ersetzen Resmio-Angaben; ältere anhand von `updated` erkennbare Antworten werden ignoriert. Fehlende optionale API-Felder löschen bereits übernommene Angaben nicht, ausdrücklich leere Tag-Listen dagegen schon. Fehlt eine aktive Buchung in einer vollständigen Antwort, bleibt ihre letzte bekannte Belegung bestehen und sie erhält einen Prüfhinweis. Erst ein Quellstatus oder authentifizierter Lösch-Webhook gibt diese Belegung frei. Lösch-Webhooks hinterlassen eine dauerhafte Kennung, damit alte Antworten die Buchung auch nach der TV-Bereinigung nicht wiederbeleben.

Reservierungskarten zeigen Tische als „Tisch 2“ bzw. „Tisch 2, 3, 5“ ohne Raumpräfix und ohne Resmio-Buchungs-ID. Überschneidungs-, Kapazitäts- und Sperrhinweise werden beim Laden mit der aktuellen Belegung neu berechnet; erledigte Konflikte warten nicht auf den nächsten Resmio-Abgleich.

Der letzte Abgleich und sichere Fehlermeldungen stehen im Reservierungsbuch. Administratoren können den Abgleich und die Tischzuordnung bedienen; `operations` kann die importierten Angaben lesen und lokale Serviceaktionen ausführen.

## Reservierungswidget für die Homepage

Öffentliche Seite: `/reservieren` auf der Dienstplan-Domain. Sie läuft ohne Anmeldung in einem eigenen Dokument, damit Styles der Homepage und des Portals getrennt bleiben. Die öffentliche API liefert ausschließlich Zeitstatus sowie Raumname/-kennung; keine Gastlisten, Kontakte, internen Notizen, Tischkennungen oder Layouts. Der Aufruf ist auf der Küchen-TV-Domain weiterhin gesperrt.

### Einrichtung und Codeschnipsel

Nach Merge/Pull:

```bash
php artisan migrate --force
npm run build
php artisan optimize:clear
php artisan queue:restart
```

Unter **Reservierungsregeln → Reservierungswidget für die Homepage** die Online-Regeln prüfen, die eigene Datenschutzhinweis-URL eintragen und Online-Reservierungen aktivieren. Nach der Migration ist die öffentliche Buchung zunächst ausgeschaltet. Verfügbar sind Gruppengrenzen, Vorlauf, Buchungshorizont, Raumauswahl, Warteliste sowie automatische/manuelle/größenabhängige Bestätigung. Die internen Öffnungstage, erste/letzte Startzeit, Intervalle und gruppenabhängige Aufenthaltsdauer gelten auch online. Die Online-Regeln liegen getrennt von den internen Regeln und besitzen eine eigene Revision, verwenden beim Speichern aber dieselbe Buchungssperre.

Der Abschnitt erzeugt einen direkt kopierbaren iframe-Schnipsel. `APP_URL` muss auf die öffentliche Dienstplan-Adresse zeigen. Maximalbreite und Höhe können vor dem Kopieren eingestellt oder anschließend im HTML angepasst werden. Beispiel für einen WordPress-Block „Individuelles HTML“:

```html
<iframe
  src="https://dienstplan.hemingway-hilden.de/reservieren"
  title="Tisch reservieren im Hemingway"
  style="display:block;margin:0 auto;width:100%;max-width:520px;height:760px;border:0;border-radius:18px;"
  loading="lazy">
</iframe>
```

Die Breite bleibt responsiv. Bei kleiner Höhe lassen sich Zeiten bzw. Formularinhalt innerhalb des Widgets scrollen. Standardmäßig dürfen `https://hemingway-hilden.de`, `https://www.hemingway-hilden.de` und die eigene Portal-Domain das Widget einbetten. Weitere HTTPS-Ursprünge können in `RESERVATION_WIDGET_ORIGINS` kommasepariert ohne Pfad oder abschließenden Slash eingetragen werden; danach Konfigurationscache erneuern. Falls Plesk zusätzliche globale `X-Frame-Options`-/CSP-Header setzt, dürfen sie die Homepage-Einbettung von `/reservieren` nicht blockieren. Die Einbettungsfreigabe gilt nur für diese öffentliche Seite, nicht für interne Verwaltungsseiten.

Den bisherigen öffentlichen Resmio-Schnipsel durch dieses Widget ersetzen. Buchungen entstehen nur im lokalen Reservierungsbuch und werden nicht an Resmio zurückgesendet. Importierte Resmio-Belegungen entsprechen dem letzten erfolgreichen Abgleich; andere weiterhin aktive öffentliche Resmio-Kanäle kennen die neuen lokalen Buchungen nicht. Für den Übergang die Resmio-Daten vollständig abgleichen, den Importhorizont mindestens so groß wie den Online-Horizont halten und keine unabhängigen öffentlichen Buchungssysteme auf dieselben Plätze anbieten.

### Gastablauf und Verfügbarkeit

1. Gästezahl, Datum und optional Raum wählen. Freie Uhrzeiten sind direkt auswählbar; eine Sanduhr kennzeichnet Wartelistenzeiten. Geschlossene Wochentage, Vorlauf, Horizont und explizite Restaurant-/gewählte Raumsperren werden berücksichtigt.
2. Name, Telefonnummer, E-Mail und optional Wünsche eingeben; Datenschutzhinweise bestätigen. Es werden keine Tischnummern oder manuellen Überbelegungen öffentlich angeboten.
3. Die Rückmeldung unterscheidet **Tisch reserviert**, **Anfrage eingegangen** und **Auf der Warteliste** und zeigt eine Buchungsnummer. Warteliste und manuelle Anfrage werden ausdrücklich nicht als bestätigte Reservierung bezeichnet. Die Bestätigung erscheint im Widget. Bei aktiviertem Reservierungsmailversand erhalten Gäste zusätzlich die passende Rückmeldung per E-Mail.

Die Zeitübersicht verwendet einen beschränkten Tagesschnappschuss. Beim Absenden werden Regeln, Uhrzeit und gesamte Aufenthaltsdauer unter der gemeinsamen Datenbanksperre erneut geprüft und ein freier Einzeltisch oder eine hinterlegte Kombination automatisch zugeordnet. Manuelle Zusatzstühle umgehen die Online-Kapazität nicht. Bei zwischenzeitlich geänderter Verfügbarkeit erhält der Gast HTTP 409 mit verständlicher Rückmeldung und kann die Zeiten neu wählen; seine Kontaktdaten bleiben im Formular erhalten.

Ein Wartelisteneintrag ist nur zu einer tatsächlich als Warteliste angebotenen Zeit möglich und hält keine Tische frei. Der Raumwunsch bleibt für die spätere interne Übernahme erhalten. Neue Buchungen tragen die Quelle „Website“; Gastwünsche stehen separat als Gastkommentar im Buchungsdatensatz und sind in der kompakten Liste aufklappbar. Lokale Bearbeitung und Wartelistenübernahme funktionieren wie bei internen Einträgen.

### Wiederholungen und Zugriff

Jeder Absendeversuch besitzt einen zufälligen Anfrageschlüssel. Ein identischer erneuter POST liefert dieselbe Buchung zurück; eine geänderte Nutzlast mit demselben bereits verwendeten Schlüssel wird zurückgewiesen. Der eindeutige Datenbankindex und die gemeinsame Transaktion verhindern doppelte Einträge. Schlüssel und Nutzlasthash werden nicht in Buchungs-JSON-Antworten veröffentlicht.

Die öffentlichen Endpunkte sind `GET /api/public/reservations/slots` und `POST /api/public/reservations/book`. Sie benötigen keine Drittanbieter-Cookies oder Portal-Anmeldung. Getrennte IP-Limits begrenzen die Zeitenabfrage auf 60/min und das Absenden auf 6/min; zusätzliche Felder wie clientseitiger Status, Tischwahl, Aufenthaltsdauer oder Quelle werden nicht übernommen. Ein Honeypot, Kontakt-/Datumsvalidierung und serverseitige Regeln ergänzen die Begrenzung. Die Zustimmung wird mit Zeitpunkt und dem zu diesem Zeitpunkt geltenden Datenschutzlink gespeichert; es wird kein Marketingeinverständnis angefordert.

## Reservierungsmails

Neue Website-Buchungen erhalten nach Aktivierung eine Bestätigung, einen ausdrücklich unbestätigten Anfrageneingang oder einen Wartelisteneingang. Die spätere interne Bestätigung bzw. Wartelistenübernahme versendet eine Bestätigung. Änderungen an Datum, Startzeit, Gästezahl, Gastname oder Empfängeradresse und Stornierungen erzeugen passende Nachrichten. Eine stornierte Warteliste hat einen eigenen Text. Interne Notizen, reine Tischwechsel und die Servicestatus Eingetroffen/Fertig/No-Show lösen keine Mails aus. Resmio-Importe und telefonisch/manuell angelegte Datensätze werden nicht zusätzlich angeschrieben. Bereits vor Aktivierung vorhandene Buchungen werden nicht pauschal nachversendet; neue relevante Änderungen an Website-Buchungen werden berücksichtigt.

HTML- und Textvorlagen liegen unter `resources/views/mail/reservations/`. Sie zeigen nur Gastname, Datum/Startzeit in Europe/Berlin, Gästezahl und Buchungsnummer. Interne Notizen, Gastkommentare, Kontakte anderer Gäste und Tischnummern sind nicht enthalten. Für Änderungen kann der Gast auf die Mail antworten. Buchungsmails enthalten zusätzlich einen persönlichen Stornolink mit Bestätigungsseite; bestätigte Website-Buchungen können automatisch erinnert werden (siehe unten).

### Aktivieren auf dem Server

Migration und Build wie oben ausführen. In der Server-`.env` die vorhandenen SMTP-Daten prüfen und ergänzen, zum Beispiel mit den eigenen Zugangsdaten statt der Platzhalter:

```dotenv
RESERVATION_MAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=SMTP-SERVER
MAIL_PORT=587
MAIL_USERNAME=POSTFACH
MAIL_PASSWORD="PASSWORT"
MAIL_FROM_ADDRESS=ABSENDERADRESSE
MAIL_FROM_NAME="Hemingway Hilden"
RESERVATION_MAIL_REPLY_TO=ANTWORTPOSTFACH
MAIL_TIMEOUT=30
```

`MAIL_SCHEME=smtp` nutzt mit einem entsprechend konfigurierten Server STARTTLS; bei implizitem TLS z. B. auf Port 465 `MAIL_SCHEME=smtps` verwenden. Port und Modus nach den eigenen Postfach-Einstellungen setzen. Absender und Antwortpostfach müssen eigene erreichbare Adressen sein; keine Zugangsdaten im Repository speichern. Danach `php artisan optimize:clear` (bei verwendetem Konfigurationscache anschließend `php artisan config:cache`).

Der bestehende Laravel-Scheduler muss **jede Minute** laufen (`php artisan schedule:run` über die Plesk-Aufgabenplanung mit dem verwendeten PHP-Binary und dem Projektpfad). Der Mailausgang wird dann jede Minute verarbeitet. Dafür ist kein Queue-Worker erforderlich. Ein manueller Lauf ist `php artisan reservations:send-mails --limit=25`. Die im Browser erzeugte Bestätigung ist unabhängig davon sofort verfügbar. Ohne aktivierten Versand wird im Widget keine zusätzliche E-Mail angekündigt. `log`/`array`-Mailer und Platzhalter-Absender gelten nicht als echter Versand; ein aktivierter, aber falsch konfigurierter Versand lässt vorgemerkte Mails unverändert warten.

Unter **Reservierungsregeln → E-Mails an Reservierungsgäste** sehen Administratoren Konfigurationsstatus, Zähler und bis zu 20 Nachrichten (Fehler zuerst). „An Mailserver übergeben“ bedeutet SMTP-/Transportannahme, nicht nachgewiesene Zustellung im Postfach. Für den Echtbetrieb eine eigene Testreservierung an ein kontrolliertes Postfach durchführen und Empfang/Spamordner sowie Antworten prüfen. Diese Prüfung ist lokal nur mit gefälschtem Mailtransport erfolgt; echte SMTP-Zugangsdaten wurden weder benötigt noch verwendet.

### Mailausgang, Fehler und Wiederholungen

Die Mail wird innerhalb derselben Datenbanktransaktion wie die Buchung im dauerhaften `reservation_mail_outbox` gespeichert; SMTP findet erst im separaten Versandlauf statt. Scheitert das Speichern, entstehen weder Buchung noch Mail. Ein identischer Widget-Retry erzeugt nur eine Buchung und einen Mailauftrag. Ein eindeutiger Index aus Buchung und Version verhindert wiederholte Aufträge bei derselben Änderung. Der gespeicherte Snapshot enthält nur die benötigten Mailangaben. Ein überholter Termin-/Status-/Empfängerstand wird vor dem Versand übersprungen, sodass beispielsweise eine noch ausstehende Bestätigung nach einem Storno nicht mehr gesendet wird.

Ein Versandfehler ändert die Reservierung nicht. Bis zu acht automatische Versuche mit wachsendem Abstand; danach steht die Mail als fehlgeschlagen im Verwaltungsbereich und kann von einem Administrator erneut vorgemerkt werden. Eine Verarbeitungssperre mit Token verhindert parallelen Versand desselben Auftrags; nach zehn Minuten kann ein abgebrochener Lauf wieder aufgenommen werden. Versuche verwenden dieselbe Message-ID. Zwischen erfolgreicher SMTP-Annahme und Speicherung des Erfolgs bleibt bei einem Prozessabbruch dennoch eine seltene Doppelzustellung möglich; SMTP bietet hier keine transaktionale Genau-einmal-Zustellung. Fehlertexte enthalten keine rohen SMTP-Antworten oder Zugangsdaten. Ein reiner interner Notizwechsel bewahrt den bisherigen Buchungsstatus und erzeugt keine Statusmail.

### Persönliche Stornolinks

Buchungs-, Anfrage-, Wartelisten- und Erinnerungsmails enthalten einen signierten persönlichen Link. Die Adresse wird immer aus `APP_URL` erzeugt; eingehende Host-Header werden nicht als Mailziel verwendet. Der Link gilt bis zum Reservierungsbeginn und ist an Termin, Gästezahl und Empfängeradresse gebunden. Nach entsprechenden Änderungen gilt nur der Link aus der neuesten Mail. Interne Notiz- und Tischänderungen lassen ihn gültig. Bereits vor dieser Erweiterung zugestellte Mails lassen sich nicht nachträglich ergänzen; neue Änderungs-/Erinnerungsmails enthalten den Link.

`GET /reservieren/storno/{booking}` zeigt eine schlanke Bestätigungsseite ohne Name, E-Mail, Telefon oder interne Angaben. Ein bloßer Aufruf durch den Gast oder einen Mail-Linkscanner ändert nichts. Erst ein POST mit CSRF-Token und ausdrücklicher Bestätigung storniert. Signatur, aktueller Buchungsstand und erlaubter Status werden unter der gemeinsamen Kapazitätssperre nochmals geprüft. Wiederholtes Stornieren vor Ablauf des Links ist idempotent. Die Buchung wechselt auf Storno, ihre Tische werden sofort für neue Belegungen frei, das Ereignis wird als `guest_cancelled` protokolliert und die bestehende Stornomail wird vorgemerkt.

Online stornierbar sind Website-Reservierungen mit Status Angefragt/Bestätigt und wartende Website-Einträge. Eingetroffen, Fertig und No-Show sowie Resmio-Buchungen sind ausgeschlossen. Die Seite ist ohne Anmeldung erreichbar, auf der TV-Domain gesperrt, nicht einbettbar und mit No-Store/No-Index/No-Referrer versehen. Sie lädt keine externen Inhalte. Links sind persönliche Zugangsberechtigungen und sollten nicht weitergeleitet werden.

### Einstellbare Erinnerungen

Unter **Reservierungsregeln → E-Mails an Reservierungsgäste → Erinnerungsmail** können Administratoren Erinnerungen aktivieren und 1–168 Stunden Vorlauf wählen. Standard sind **aktiv, 24 Stunden**; der globale serverseitige Mailversand muss ebenfalls eingeschaltet und konfiguriert sein. Die Einstellungen besitzen eine eigene Revision. Migration und Build ausführen; weitere Cronjobs sind nicht nötig: `reservations:send-mails` merkt jetzt vor dem Versand auch fällige Erinnerungen vor.

Erinnert werden ausschließlich weiterhin bestätigte Website-Reservierungen mit gültiger E-Mail und zukünftigem Beginn. Die Buchung muss bereits vor dem Erinnerungszeitpunkt bestätigt worden sein und eine Bestätigungs-/Änderungsmail für den aktuellen Termin und Empfänger muss bis dahin erfolgreich an den Mailserver übergeben worden sein. So erhalten kurzfristige Buchungen oder verspätet zugestellte Erstbestätigungen nicht unmittelbar eine zweite Mail. Alte importierte oder vor Einführung des Mailversands angelegte Buchungen ohne passenden Versandnachweis werden nicht pauschal angeschrieben.

Der Scheduler holt eine fällige Erinnerung höchstens 60 Minuten verspätet nach. Vor dem eigentlichen Versand werden Status, aktueller Termin, Empfänger und Erinnerungseinstellung erneut geprüft; überholte oder zu spät gewordene Aufträge werden übersprungen. Pro Buchung, Starttermin und Empfänger entsteht maximal ein Erinnerungsauftrag, auch bei mehrfachen Scheduler-Aufrufen oder internen Notizänderungen. Gastname/Gästezahl werden vor dem Versand aktualisiert. Eine neue Termin- oder Empfängerzuordnung kann einen neuen Auftrag bekommen; eine Terminverschiebung setzt den Bestätigungszeitpunkt neu. Ein bereits übersprungener oder zugestellter Auftrag wird durch bloßes Aus-/Einschalten nicht erneut angelegt. Die Zeitberechnung berücksichtigt Europe/Berlin und die Sommerzeitumstellung.

## Vereinbarte weitere Entwicklung

Zielumfang: funktionale Orientierung an Resmio Ultimate samt Add-ons; die Funktionen der iPad-App werden in die Gesamtlösung integriert. Builder und interner Reservierungskern sind umgesetzt. Der weitere Ultimate-Umfang bleibt als Ziel bestehen.

Geplante Bausteine:

1. Ausbau der Regeln um Ausnahmen/Feiertage, wiederkehrende Sperren und weitere Kapazitätsgrenzen.
2. Wochen-/Agenda-/Zeitstrahlansicht, Serienreservierungen und weitere Komfortfunktionen für den vorhandenen Tagesbetrieb.
3. Ausbau der Gastverwaltung und Nachrichten. Persönliche Gast-Stornierungen und Erinnerungen sind umgesetzt. Das einbettbare Online-Widget und transaktionale Gastmails sind umgesetzt.
4. Anzahlungen, No-Show-Abwicklung, Automatisierungen, Auswertungen und bestehende Dienstplan-/TV-Anbindung.
5. Marketing, Feedback, Erweiterung bestehender Gutscheine/Tickets sowie weitere vereinbarte Module. Externe Buchungskanäle, SMS, Telefonassistent und Zahlungsdienste benötigen eigene Integrationen.

Die öffentliche Online-Buchung kann mit dem oben beschriebenen Widget freigeschaltet werden; Gästemails lassen sich wie im Abschnitt „Reservierungsmails“ serverseitig aktivieren. Bestehende Resmio-Buchungen können mit dem beschriebenen Abgleich übernommen werden. Neue lokale Buchungen werden nicht an Resmio übertragen; Resmio kennt deren Belegung daher nicht. Ein öffentlicher Parallelbetrieb beider Buchungssysteme benötigt weiterhin eine gesonderte Umschaltung oder gemeinsame Verfügbarkeitsprüfung.

### Aus den Screenshots übernommene Werte

- Online einstellbar: automatische Bestätigung bis 8 Personen; online 1–8 Personen. Die Widget-Konfiguration startet mit automatischer Bestätigung aller erlaubten Gruppen und kann auf die Größenregel umgestellt werden. Interne Reservierungen werden bestätigt angelegt.
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
php artisan test --filter='ReservationBookTest|FloorPlanTest|ResmioBookingSyncTest|ResmioContractTest|ResmioDiagnosticsTest|ReservationWidgetTest|ReservationMailTest|ReservationGuestActionsTest'
node --test tests/Workflow/floor-plan.test.mjs tests/Workflow/reservation-ui.test.mjs tests/Workflow/reservation-plan.test.mjs tests/Workflow/table-selection.test.mjs
npm run build
```

Die Featuretests verwenden die Benutzer-/Berechtigungs-, Builder- und Buchungs-Migrationen samt benötigten Abhängigkeiten isoliert. Eine bestehende HACCP-Migration verwendet MySQL-spezifisches `SHOW INDEX` und blockiert ansonsten den SQLite-Testlauf unabhängig vom Builder. Die Gesamtanwendung wird damit nicht als vollständig getestet ausgewiesen.
