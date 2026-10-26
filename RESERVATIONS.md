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

## Vereinbarte weitere Entwicklung

Zielumfang: funktionale Orientierung an Resmio Ultimate samt Add-ons; die Funktionen der iPad-App werden in die Gesamtlösung integriert. Builder und interner Reservierungskern sind umgesetzt. Der weitere Ultimate-Umfang bleibt als Ziel bestehen.

Geplante Bausteine:

1. Ausbau der Regeln um Ausnahmen/Feiertage, wiederkehrende Sperren und weitere Kapazitätsgrenzen.
2. Wochen-/Agenda-/Zeitstrahlansicht, Serienreservierungen und weitere Komfortfunktionen für den vorhandenen Tagesbetrieb.
3. Online-Widget, Gastverwaltung, Bestätigungen, Erinnerungen, Nachrichten und Stornierungen.
4. Anzahlungen, No-Show-Abwicklung, Automatisierungen, Auswertungen und bestehende Dienstplan-/TV-Anbindung.
5. Marketing, Feedback, Erweiterung bestehender Gutscheine/Tickets sowie weitere vereinbarte Module. Externe Buchungskanäle, SMS, Telefonassistent und Zahlungsdienste benötigen eigene Integrationen.

Die öffentliche Online-Buchung und Gästemails werden durch diesen Ausbau noch nicht aktiviert. Bestehende Resmio-Buchungen können mit dem unten beschriebenen Abgleich übernommen werden. Neue lokale Buchungen werden nicht an Resmio übertragen; Resmio kennt deren Belegung daher nicht. Ein öffentlicher Parallelbetrieb beider Buchungssysteme benötigt weiterhin eine gesonderte Umschaltung oder gemeinsame Verfügbarkeitsprüfung.

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
php artisan test --filter='ReservationBookTest|FloorPlanTest|ResmioBookingSyncTest|ResmioContractTest|ResmioDiagnosticsTest'
node --test tests/Workflow/floor-plan.test.mjs tests/Workflow/reservation-ui.test.mjs tests/Workflow/reservation-plan.test.mjs tests/Workflow/table-selection.test.mjs
npm run build
```

Die Featuretests verwenden die Benutzer-/Berechtigungs-, Builder- und Buchungs-Migrationen samt benötigten Abhängigkeiten isoliert. Eine bestehende HACCP-Migration verwendet MySQL-spezifisches `SHOW INDEX` und blockiert ansonsten den SQLite-Testlauf unabhängig vom Builder. Die Gesamtanwendung wird damit nicht als vollständig getestet ausgewiesen.
