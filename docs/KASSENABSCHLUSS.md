# Kassenabschluss und Schichtübergabe

## Einrichtung und Auslieferung

Vor der Installation Datenbank und bestehende Anwendung sichern. Zunächst auf einer Testinstallation mit Kopie der Daten prüfen.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
```

Die neue Migration ergänzt eigene Tabellen; vorhandene Zeitstempel, Lohnexporte und historische Auszahlungsmarkierungen werden nicht importiert oder verändert. Die alten Barauszahlungsdaten dürfen nicht zusätzlich in neue Abschlüsse übernommen werden, ohne ihren tatsächlichen Zahlungszeitpunkt zu prüfen.

Navigation: **Schichtübergabe**, **Tagesabschluss**. Der Wechselgeld-Sollbestand steht unter **Einstellungen / Allgemein** (Standard 250 EUR). Eine Änderung gilt für neu eröffnete Geschäftstage. Der erste Geschäftstag benötigt einen tatsächlich gezählten Anfangsbestand, Folgetage übernehmen den tatsächlichen Restbestand. Es kann jeweils nur ein Geschäftstag offen sein; Abschlüsse müssen chronologisch erfolgen.

Die neuen JSON-Endpunkte `/api/cash/*` sowie `/api/settings` liegen absichtlich in der Web-Middleware: Anmeldung und CSRF-Schutz sind erforderlich. Berechtigt sind die vorhandenen angemeldeten Anwendungsnutzer; ein zusätzliches Rollenmodell wurde nicht eingeführt. Die übrigen vorhandenen API-Endpunkte und die bestehende Benutzerregistrierung sind nicht Teil dieser Rechteänderung.

## Geldbewegungen und Berechnung

Alle Geldbeträge werden in ganzen Cent gespeichert. Mitarbeitereingaben werden auf Minuten umgerechnet; Stunden sind Dezimalstunden (7,5 = 7 Stunden 30 Minuten). Hinterlegter Stundensatz, vorgeschlagener Betrag, tatsächlich bestätigter Betrag und Auszahlungsstatus werden separat gespeichert. Diese Angaben ändern weder Zeiteinträge noch den Lohnexport.

Für eine Übergabe:

```
Erwartet = tatsächlicher Anfangsbestand
         + Bar Soll aus den neu abzurechnenden Bediener-X-Berichten
         + Einlagen
         - Einkäufe und sonstige Ausgaben/Entnahmen
         - tatsächlich bestätigte Barauszahlungen
         - noch nicht in einer vorherigen Übergabe berücksichtigte Gutscheineinlösungen
Differenz = gezählt vor Umschlagentnahme - erwartet
Restbestand = gezählt vor Umschlagentnahme - tatsächlicher Umschlag
Auffüllbedarf = max(0, Wechselgeld-Soll - Restbestand)
```

Kartentrinkgeld wird im Betrieb aus dem gemeinsamen Bargeld ausgezahlt und ist bereits im Bar-Soll der Kasse berücksichtigt. Es darf nicht erneut als Ausgabe erfasst werden. Die Umsatzfelder enthalten keine Trinkgelder. Bar-/Karte-Umsatz und Bar-/Karte-Soll werden getrennt gespeichert.

Ein X-Bericht ist kumulativ. Bei weiteren Übergaben müssen die Differenzen zum vorherigen Stand verwendet werden. Die Berichtsreferenz dokumentiert den Zeitraum; das Programm kann aus manuell eingegebenen Summen keine automatische Überschneidungsprüfung ableiten. Bedienerumsätze und fremdkassierte Tische sind nicht identisch; für Bargeld ist Bar-Soll maßgeblich. Gutscheinverkäufe „Mehrzweck“ werden separat erfasst (siehe unten). Gutscheineinlösungen werden über die Gutscheinliste erfasst und separat vom erwarteten Bargeld abgezogen. Weitere Finanzwege sind nicht Bestandteil dieses Eingabemodells.

Übergaben sind nach Bestätigung unveränderlich und durch eine UUID gegen doppelte Speicherung geschützt. Die Umschlagnummer lautet `SU-{handover_id}`; sie bleibt auch bei 0 EUR als Referenz der Übergabe bestehen. Korrekturen vor dem Tagesabschluss müssen als zusätzliche, begründete tatsächliche Bewegungen erfasst werden; eine falsch eingegebene Berichtssumme wird nicht durch eine fiktive Geldbewegung korrigiert. Solche Datenfehler vor Freigabe administrativ prüfen.

Im Tagesabschluss sind Mitarbeiter-/Ausgabenfelder **nur Ergänzungen**, bereits in Übergaben erfasste Vorgänge werden nicht erneut eingetragen. Für die verbleibende Kassenrechnung wird vom Tages-Bar-Soll die Summe der bereits abgerechneten Übergabe-Bar-Sollbeträge abgezogen. Der Anfangsbestand dieser Restrechnung ist der letzte Übergabe-Restbestand. Dadurch werden Übergaben, Auszahlungen und Umschläge nicht doppelt verrechnet. Zusätzliche Umschlagentnahme beim Tagesabschluss: `TA-{day_id}`.

Kassendifferenzen benötigen einen Vermerk. Der Z-Bericht prüft Netto + Steuer = Brutto, Steuerumsätze = Barumsatz + Kartenumsatz sowie Barumsatz + Kartenumsatz + Gutscheinverkauf = Bar Soll + Karte Soll. Z-Nummer/Kasse sowie sich überschneidende Z-Berichtszeiträume werden gegen bereits abgeschlossene Tage geprüft. Der Berichtszeitraum wird in Berlin eingegeben, in UTC gespeichert und ist am Ende exklusiv.

Der gespeicherte Entwurf wird separat endgültig abgeschlossen. Ein Abschluss ist ein unveränderlicher Snapshot und kann als PDF exportiert werden. Keine nachträglichen stillen Änderungen; eine fachliche Storno-/Korrekturversion für bereits abgeschlossene Tage ist noch nicht implementiert. Daher zunächst mit Testdaten abnehmen. Gespeicherte Daten regelmäßig sichern.

## Gutscheinverkauf (Mehrzweck)

In jedem Bedienerbericht der Schichtübergabe und im Z-Bericht des Tagesabschlusses gibt es das Feld **Gutscheinverkauf (Mehrzweck)**. Ohne Verkauf 0,00 eintragen; fehlende oder leere Werte werden aus Kompatibilitätsgründen als 0 behandelt. Negative Beträge sind nicht zulässig; eine gesonderte Stornologik ist nicht enthalten. Für Schichten gelten nur die Verkäufe seit der vorherigen Übergabe, für den Tagesabschluss die komplette Tagessumme laut Z-Bericht. Die Tages-Gutscheinsumme wird nicht um Übergaben gekürzt und nicht zusätzlich zu ihnen addiert.

Konkreter Vectron-Testfall Z-294, Bediener 1: Barumsatz 449,95 EUR, Kartenumsatz 818,40 EUR, Gutscheinverkauf 40,00 EUR, Bar Soll 432,85 EUR, Karte Soll 875,50 EUR. Die Prüfung lautet:

```
449,95 + 818,40 + 40,00 = 432,85 + 875,50 = 1.308,35 EUR
```

Das unveränderte Bar Soll ist die Grundlage der Bargeldrechnung. Der Verkauf ist darin bereits berücksichtigt; **keine zusätzliche Bareinlage und keine nochmalige Addition des Gutscheinbetrags zum erwarteten Bargeld**. Dasselbe gilt bei mit Karte bezahlten Gutscheinverkäufen: Die tatsächliche Verteilung steht bereits in Bar Soll/Karte Soll. Der Gutscheinverkauf wird nicht als 7-/19-%-Umsatz und nicht als künstlicher 0-%-Umsatz erfasst.

`voucher_sales_cents` wird separat in jedem Übergabe-Bericht und im `z_report` gespeichert, in beiden PDFs ausgewiesen und im bestehenden n8n-Abschluss-Payload mitgegeben. Alte Datensätze ohne Feld werden mit 0 angezeigt; bestehende abgeschlossene Snapshots bleiben unverändert. Die Gutscheinverwaltung ergänzt eigene Tabellen per Migration; am FTP-Workflow ist keine Änderung nötig. Die allgemeine Vereinfachung der Steuerfelder ist nicht Teil dieser Änderung.

## Gutscheinliste, Formularnummern und Teil-Einlösung

Menü **Kasse & Abrechnung → Gutscheinverkauf**, URL `/kasse/gutscheinverkauf`.

1. Gutscheinverkauf wie bisher in der Schichtübergabe erfassen und diese bestätigen. Für jeden Bedienerbericht mit Gutscheinverkauf entsteht automatisch ein offener Verkaufseintrag mit Geschäftstag und Gesamtsumme. Bei wiederholter Übertragung derselben Übergabe entsteht kein zweiter Eintrag.
2. Unter **Verkäufe ergänzen** die aufgedruckte fortlaufende Formularnummer, den Einzelwert und Bar/Karte eingeben. Ein Gutschein ist mit der gesamten Summe vorbelegt. **Weiterer Gutschein** teilt einen Sammelverkauf auf mehrere Formulare auf; die Einzelbeträge müssen exakt zur übernommenen Summe passen. Tag und Gesamtbetrag werden nicht frei verändert. Formularnummern bestehen aus Ziffern; führende Nullen werden angezeigt, aber bei der Eindeutigkeitsprüfung und Suche gleichgesetzt (`0001` = `1`). Diese Nummer ist unabhängig vom Vectron-Code.
3. Nach Bestätigung steht der Gutschein mit ursprünglichem Wert, Restguthaben und Verkaufsnachweis in der Liste. Der Tagesabschluss verlangt die vollständige Nummerierung aller seit Einführung der Funktion erzeugten offenen Verkäufe.
4. Einlösung: Gutschein über seine Nummer suchen, Teilbetrag oder **Restbetrag übernehmen** wählen, prüfen und verbindlich bestätigen. In Vectron weiterhin als Barzahlung abschließen. Es muss ein offener Geschäftstag bestehen; dieser wird automatisch übernommen. Bei Restguthaben > 0 lautet der Status **Teilweise eingelöst**, bei 0 **Entwertet**. Überziehung, unbekannte Nummern und erneute Nutzung eines vollständig entwerteten Gutscheins werden abgelehnt.
5. Eine Einlösung vermindert das Restguthaben sofort. Sie wird mit Geschäftstag, Betrag, Benutzer und Zeitpunkt protokolliert. Die nächste bestätigte Übergabe übernimmt alle noch offenen Einlösungen dieses Tages und zieht sie genau einmal vom erwarteten Bargeld ab. Der Tagesabschluss übernimmt nur danach hinzugekommene Einlösungen. **Keine zusätzliche manuelle Entnahme erfassen.** Bar Soll aus Vectron unverändert übernehmen.

Wenn nach einer gespeicherten Tageszählung ein Gutschein eingelöst wird, muss die Zählung neu eingegeben und der Abschluss erneut gespeichert werden. Einlösungen erhöhen die Tagesrevision; eine bereits geöffnete alte Abrechnungsansicht muss neu geladen werden. Geldbewegungen und Mitarbeiterdaten werden dabei nicht gelöscht. Dieselbe Einlöseanfrage kann bei einem Netzwerkfehler wiederholt werden, ohne das Guthaben erneut zu mindern. Datenbanktransaktionen und die gemeinsame Kassenstatus-Zeilensperre serialisieren parallele Vorgänge.

Der gespeicherte Z-Bericht kann ebenfalls offene Verkäufe erzeugen, soweit dessen Tagessumme über die bereits bestätigten Schichtverkäufe hinausgeht. Es wird nur dieser zusätzliche Betrag erfasst. Wiederholtes Speichern erzeugt keine Duplikate. Noch nicht nummerierte Restbeträge dürfen korrigiert werden; bereits nummerierte Verkäufe können nicht stillschweigend verändert oder einer anderen Abrechnung zugeordnet werden. Die Tages-Gutscheinsumme darf nicht unter der Summe der bestätigten Schichten liegen.

Gutscheine und Einlösungen sind nach Bestätigung unveränderlich; diese Version bietet keine Storno- oder Umbuchungsfunktion. Es gibt keinen Import und keine automatische Anlage alter Gutscheinbestände. Die Migration legt nur leere Registertabellen an; historische Abschluss-Snapshots werden nicht verändert oder nachträglich nummeriert.

### Speicherung und Berichte

`cash_voucher_batches` hält die aus Abrechnungen übernommenen Verkaufssummen, `cash_vouchers` die Formularnummern und Restguthaben, `cash_voucher_redemptions` die einzelnen Einlösungen und deren Zuordnung zur Übergabe. Alle Geldbeträge sind ganze Cent. Die Oberfläche ist wie die bestehenden Kassenfunktionen nur nach Anmeldung nutzbar und verwendet denselben CSRF-Schutz.

Schicht-PDFs weisen übernommene Einlösungen mit Nummer und Betrag aus. Der endgültige Tages-Snapshot enthält zusätzlich `voucher_sales` mit den verkauften Nummern, Werten und Zahlarten; Einlösungen stehen in den jeweiligen Schicht-Snapshots beziehungsweise unter `supplement.voucher_redemptions`. `calculation.voucher_redemptions_cents` beziehungsweise `closing.voucher_redemptions_cents` bezeichnet nur den in diesem Rechnungsschritt abgezogenen Betrag. Diese Felder werden auch im bestehenden n8n-Abschluss-Payload geliefert. Einlösungen dürfen vom Empfänger nicht noch einmal als allgemeine Entnahme hinzugebucht werden.

Vor Bereitstellung Datenbank sichern. Deployment einschließlich **`php artisan migrate --force`**, Frontend-Build und geleerten Routen-/Anwendungscaches durchführen; das vorhandene `scripts/deploy.sh` erledigt diese Schritte. Danach mit einem neuen Verkauf und zwei Teil-Einlösungen testen. Die beiden bestehenden n8n-FTP-Archivworkflows bleiben unverändert und verwenden die ergänzten PDFs.

## Vectron BI

Offizielle Dokumentation:

- https://demo-backend.vectron.cloud/business-data-api/swagger-ui/index.html
- https://demo-backend.vectron.cloud/v3/api-docs/business-data-api
- https://vectronhelp.zendesk.com/hc/de/articles/39335893737501

Serverseitig in `.env` setzen, niemals Schlüssel einchecken:

```dotenv
# Tatsächliche Produktions-Basisadresse von Vectron bestätigen lassen.
# Beispiel nur für das Format; keine Demo-Adresse für Produktionsschlüssel nutzen.
VECTRON_BI_BASE_URL=https://PRODUKTIONSHOST/business-data-api
VECTRON_OPERATOR_ID=
VECTRON_SITE_ID=
VECTRON_API_KEY=
VECTRON_PARTNER_API_TOKEN=
```

Der Händler besitzt Betreiber-ID und Händler-API-Key. Laut aktueller Vectron-Beschreibung wird zusätzlich der BI-Partner-Token benötigt. Aktiviertes Filialmodul und gültige Produktionsadresse sind separat zu prüfen. Reale Zugangsdaten waren bei der Entwicklung nicht verfügbar; alle HTTP-Tests verwenden kontrollierte Testantworten.

Header: `X-Authorization-Token` (Händler-Key), `X-API-Token` (Partner-Token). Ohne Site-ID ruft der Client die Filialen über `GET /v3/operator/{operatorId}/sites` ab. Danach die gewünschte `VECTRON_SITE_ID` setzen.

`GET /v3/site/{siteId}/transactions?id={lastInvoiceId}` liefert Rechnungen paketweise, älteste zuerst. Der Import speichert Originalantworten und schreibt den Cursor atomar fort. Wiederholte Rechnungs-IDs werden nicht dupliziert. **Vectron-Daten abrufen** wiederholen, bis `caught_up=true` gemeldet wird; ein importiertes Paket bedeutet noch keinen vollständigen Tagesimport. Zwischengespeicherte Daten sind per Filiale getrennt. Verteilte Installationen benötigen einen gemeinsamen Cache mit Locks.

**Importierte Rechnungen vergleichen** zeigt Anzahl und Kontrollsumme des ausgewählten Zeitraums. Das ist ausdrücklich kein Z-Bericht und keine verbindliche Steueraufteilung. Die geprüfte BI-Spezifikation enthält keinen Z-Bericht-Endpunkt. Separate Rechnungsrabatte/Zuschläge bei mehreren Steuersätzen und Rechnungsstornos erfordern Abstimmung. Deshalb werden Original-Z-Werte derzeit manuell übernommen und als `manual_z_report` gekennzeichnet; kein automatisches Überschreiben und keine behauptete vollständige Z-Abstimmung.

## n8n-Schnittstelle

```dotenv
CASH_N8N_WEBHOOK_URL=https://N8N-HOST/webhook/cash-day
CASH_N8N_TOKEN=
# Für asynchrone Zustellung:
QUEUE_CONNECTION=database
```

Nach Konfigurationsänderung `php artisan optimize:clear` ausführen. Bei Datenbank-Queue einen Queue-Worker betreiben:

```sh
php artisan queue:work --tries=3 --timeout=60
```

Der endgültige Abschluss und sein Versandauftrag werden in derselben Datenbanktransaktion gespeichert. **An n8n übergeben** startet den Versand; Fehler bleiben im Status sichtbar. Der Job versucht bis zu dreimal zu senden, danach ist erneutes Anstoßen möglich. Bei `QUEUE_CONNECTION=sync` erfolgt der Versand direkt im Webrequest; automatische verzögerte Wiederholungen benötigen einen Worker.

HTTPS-POST, `Authorization: Bearer …`, Header `Idempotency-Key: {event_id}`. Beträge ausschließlich in ganzen EUR-Cent. Nutzdaten:

- `schema_version`, `event_type`, `event_id`, `day_id`, `version`, `business_date`, `currency`.
- `z_report`: Brutto/Netto/Steuer 7/19, Zahlartumsätze, Zahlart-Soll, `voucher_sales_cents` separat, Originalbelegreferenz, Zeitraum und Quelle.
- `handovers`: unveränderte Übergaben mit Bedienerwerten, Mitarbeiterstunden, Auszahlungen, Bewegungen und Umschlägen.
- `supplement`: ausschließlich zusätzliche Vorgänge des Tagesabschlusses.
- `closing`: Restrechnung nach Übergaben, letzte Differenz, zusätzlicher Umschlag, Restbestand und Auffüllbedarf.

n8n muss **vor dem Erzeugen von Buchungen anhand `event_id` deduplizieren** und die ID zusammen mit dem Verarbeitungsstatus dauerhaft speichern. Bei einer Wiederholung dieselbe Bestätigung zurückgeben, ohne Buchungen erneut anzulegen. Das System kann transportbedingte Wiederholungen nicht allein verhindern.

Erforderliche Antwort nach dauerhafter Annahme:

```json
{"accepted": true, "event_id": "DIE-EMPFANGENE-EVENT-ID"}
```

Ein bloßes HTTP 200 gilt nicht als erfolgreiche Annahme. `delivered` bedeutet von n8n angenommen, nicht bereits endgültig im Buchhaltungsprogramm verbucht. Der Workflow und die spätere Kontierung/Belegübertragung sind separat einzurichten. Mitarbeiterbeträge werden als bestätigte Zahlungen übergeben, nicht pauschal als berechneter Bruttolohn gebucht. Ausgaben und Umschläge sind getrennte Bewegungen, keine Umsatzminderung. Belegreferenzen sind enthalten; Datei-Upload und Belegdateiübertragung sind noch nicht Teil dieser Version.

## Bestehende Fehlerbehebungen

- DomPDF ist mit versionierten Composer-Abhängigkeiten installiert.
- Schichttypen besitzen eine editierbare Sortiernummer; Bildschirm und PDF verwenden dieselbe Reihenfolge.
- Dienstplan-PDF zeigt tatsächliche Mitarbeiterzeiten, berücksichtigt auch inzwischen inaktive, aber geplante Schichttypen und blendet geplante Schichten an regulären Schließtagen nicht aus.
- Maximal sechs Schichttyp-Spalten pro PDF-Teil, echte PDF-Fehler statt HTML unter einer PDF-Endung.
- Planung von Schichten über Mitternacht und Tagesversatz beim Kopieren einer Woche korrigiert.
- npm-Sperrdatei synchronisiert; Vite-Build benötigt keine lokalen Entwicklungszertifikate mehr.
- Der bestehende Lohnexport berücksichtigt nun den gesamten letzten ausgewählten Tag statt nur Mitternacht.
- Das genaue Lexware lohn+gehalt-plus-Importprofil bleibt wie vereinbart für einen späteren Schritt. Die neuen Kassenstunden werden dafür nicht verwendet.

## Prüfung

```sh
npm ci
npm run build
php vendor/bin/phpunit
```

Die Kassen-Featuretests prüfen Centberechnung, negative Bediener-Bar-Sollbeträge, Wechselgeldmangel, Einlagen, tatsächliche Auszahlung, Unabhängigkeit von Zeiteinträgen, Wiederholungen, Revisionen, endgültige Sperren, Z-Abstimmung, Vectron-Header/Cursor und n8n-Empfangsbestätigung. Tests laufen auf isoliertem SQLite, nicht auf Produktionsdaten. MySQL-Parallelität wird durch eine gemeinsame Kassenstatus-Zeilensperre serialisiert; vor Mehrbenutzerbetrieb zusätzlich auf der Zielinstallation abnehmen.
