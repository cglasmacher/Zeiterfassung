# Lexware Financial Office Plus: Buchungsstapel

## Portal

1. Belege prüfen und freigeben.
2. Unter Buchhaltung im Bereich „Lexware-Buchungsstapel“ das Belegjahr wählen und „Export vorbereiten“ anklicken.
3. Die Vorschau umfasst alle freigegebenen, noch nicht exportierten Belege dieses Kalenderjahres. Kreditorenzeile über den Gesamtbetrag, Aufteilungen, Soll-/Habenkonten und Steuerschlüssel prüfen. Die Vorschau nennt das verwendete Verrechnungskonto.
4. „Export erstellen“ speichert einen unveränderlichen CSV-Snapshot und markiert genau die geprüften Belege als exportiert. Anschließend die Datei herunterladen.
5. Dieselbe Datei kann im Exportverlauf erneut heruntergeladen werden. Dafür keinen neuen Stapel erzeugen. „Exportiert“ bedeutet hier bereitgestellt, nicht bereits in Lexware importiert.

Maximal 500 Belege und 5.000 Buchungssätze pro Stapel. Eine geänderte Vorschau, nicht mehr freigegebene Belege, fehlende aktive Konten oder unvollständige Beträge blockieren den gesamten Export. Änderungen nach der Stapelerstellung ändern die gespeicherte Datei nicht.

## Import in Lexware

Ziel ist Lexware buchhaltung/plus innerhalb Financial Office, nicht Lexware Office und nicht der Lohnexport. Die CSV wird als Text/ASCII in einen Buchungsstapel importiert. Beim ersten Import ein eigenes Profil anlegen und Feldzuordnung sowie Steuerverhalten in der Lexware-Vorschau prüfen.

- Zeichensatz: ANSI / Windows-1252, ohne UTF-8-BOM.
- Feldtrennzeichen: Semikolon; Textbegrenzung: doppeltes Anführungszeichen.
- Erste Zeile enthält Feldnamen; Datum TT.MM.JJJJ; Beträge mit Dezimalkomma und zwei Nachkommastellen, ohne Tausendertrennzeichen.
- Felder: Belegdatum, Belegnummernkreis, Belegnummer, Buchungstext, Buchungsbetrag, Sollkonto, Habenkonto, Steuerschlüssel.
- Belegdatum ist das Rechnungsdatum. Belegnummernkreis bleibt leer; die Rechnungsnummer bleibt erhalten (maximal 20 Zeichen). Buchungstext maximal 79 Zeichen.
- Automatische Vorsteuer für normale Wareneinkäufe: Schlüssel 8 bei 7 %, Schlüssel 9 bei 19 %. Exportiert wird der Gruppenbruttobetrag. Abweichungen zwischen erwarteter automatischer Gruppensteuer und gespeicherter Steuer führen zum Exportfehler, nicht zu erfundenen Ausgleichsbuchungen.
- Steuerfreie Gruppen haben einen leeren Schlüssel. Die Konteneigenschaften in Lexware müssen dazu passen; das Portal liest Lexware-Kontenfunktionen nicht aus.

### Genau eine Kreditorenbuchung pro Rechnung

Für **alle Lieferanten** gibt es je Beleg eine führende Buchung über das vollständige Rechnungsbrutto auf dem Kreditorenkonto. Ihr Buchungstext ist **exakt die Rechnungsnummer**: kein Lieferantenname, kein Präfix, keine Kürzung. Auch die Belegnummer bleibt die Rechnungsnummer. Diese Kreditorenzeile enthält keinen Steuerschlüssel.

Die bisherige CSV-Struktur aus vollständigen Soll-/Habenbuchungen bleibt bestehen. Die Verbindung erfolgt über ein ausdrücklich konfiguriertes, steuerfreies Verrechnungskonto für den Rechnungsimport:

1. Verrechnungskonto an Kreditor: gesamte Rechnung, Text nur Rechnungsnummer.
2. Aufwand bzw. Pfand/Vorsteuer an Verrechnungskonto: bisherige Gruppenbeträge, Steuerschlüssel und Buchungstexte.

Bei negativen Rechnungen/Gruppen werden Soll und Haben vertauscht; CSV-Beträge bleiben positiv. Jede Aufteilungszeile berührt ausschließlich das Verrechnungskonto und ihr Sach-/Steuerkonto. Das System prüft **je Rechnung** genau eine Kreditorenbewegung in Höhe des Rechnungsbruttos und Verrechnungssaldo null. Zwei Rechnungen mit derselben Rechnungsnummer bleiben getrennt, auch beim selben Lieferanten. Nullbelege erzeugen keine Null-OP-Buchung, sondern blockieren den Export zur gesonderten Prüfung.

Das ist eine Umsetzung über normale Gegenbuchungen, kein neu angenommenes natives Lexware-Splittformat mit Leer-/Nullkonten. Die fachliche Gesamtbuchung steht damit auf dem Kreditor; das tatsächliche OP-/Bankabgleichverhalten muss beim ersten Import in der konkreten Lexware-Installation geprüft werden.

### Pfand

1141 wird ausschließlich netto gegen das Verrechnungskonto gebucht. Die Vorsteuer wird als separater Satz 1461 gegen dasselbe Verrechnungskonto gebucht. Beide Sätze haben einen leeren Steuerschlüssel: Dafür darf in Lexware keine zusätzliche automatische Steuer entstehen. Die Lexware-Konten 1141 und 1461 müssen entsprechend eingerichtet sein. Negative Beträge werden als positive Buchungsbeträge mit vertauschtem Soll/Haben ausgegeben.

Die bekannte Gato-Testrechnung ergibt sieben CSV-Sätze: eine Kreditorenbuchung über 324,81 € (Soll Verrechnungskonto / Haben 70007), vier Wareneinkaufsgruppen und zwei Pfandsätze. Für die Pfandrückgabe stehen 2,38 € Soll Verrechnungskonto / Haben 1141 sowie 0,45 € Soll Verrechnungskonto / Haben 1461 in der Datei. 70007 kommt in genau einer Zeile mit Text RE26-TEST01 vor. Das Verrechnungskonto ist nach diesen sieben Sätzen ausgeglichen. Vor Übernahme des ersten Stapels diese Werte in Lexware abgleichen; ein realer Lexware-Import konnte in der Entwicklungsumgebung nicht ausgeführt werden.

Belegbilder anschließend bei Bedarf manuell in Lexware verknüpfen. Ein erneuter Import derselben CSV in Lexware kann dort Doppelbuchungen erzeugen; der Portal-Schutz verhindert nur einen erneuten Export derselben Belegdatensätze. Inhaltsgleiche Rechnungen aus unterschiedlichen E-Mails benötigen weiterhin die fachliche Dublettenprüfung.

## Nachweise und Grenzen

- Offizielle Feldformate und Schlüssel für buchhaltung/plus: https://tsp.haufe-lexware.com/tsp_wm_get_attachment.php?att=c676753036_Lexware-buchhaltung-Schnittstellenbeschreibung-ASCII.pdf
- Offizielles Lexware-Handbuch zum Importassistenten und Steuerberechnung bei Automatikkonten: https://www.lexware.de/fileadmin/support/handbuecher/2026/handbuch_buchhaltung_berater.pdf (pro/premium; konkrete Plus-Oberfläche vor Ort prüfen).

EUR und die im Portal vorhandenen Steuersätze 0/7/19 % sind unterstützt. Reverse Charge, EU-Sonderfälle, abweichende Wirtschaftsjahre/Leistungsperioden und Fremdwährungen benötigen eigene geprüfte Exportprofile. Der Export übermittelt keine Daten direkt an Lexware und kennt dessen tatsächliche Konteneigenschaften nicht.

## Technik und Deployment

Es werden die vorhandenen Tabellen AccountingExportBatch und AccountingDocument genutzt. Ein neuer Stapel speichert Format `lexware_ascii_ansi_single_creditor_v2`, Verrechnungskonto, CSV-Bytes als Base64 im vorhandenen JSON-Metadatenfeld, Hash sowie Dokument-/Zeilensnapshots. Alte v1-Stapel bleiben unverändert herunterladbar; sie werden nicht nachträglich neu berechnet oder nochmals exportiert. Eine Änderung des konfigurierten Kontos invalidiert offene Vorschauen, bestehende Downloads behalten ihre damaligen Bytes. Statusänderung und Snapshot werden in einer Transaktion vorgenommen; Belege sind dabei gesperrt. Download ist ausschließlich für Portaladministratoren verfügbar und prüft den gespeicherten Hash. Vorschau verändert keine Belege. Keine neuen Tabellen, keine Migration, kein neuer Seeder und keine n8n-Änderung erforderlich.

Deployment:

1. `git pull` und `npm run build`.
2. **Bestätigtes Konto 1370** als steuerfreies Verrechnungskonto für den Rechnungsimport in Lexware einrichten bzw. das vorhandene dafür bestimmte Konto verwenden. Kein DATEV-Automatikkonto, keine automatische Steuer auf diesem Konto. Das Portal kann diese Lexware-Konteneigenschaften nicht auslesen.
3. Dasselbe Konto in `accounting_accounts` mit `account_number` als Zeichenfolge, `kind = interim` und `active = true` hinterlegen. Bestehende Stammdaten nicht pauschal durch einen Seeder überschreiben. Der gezielte Befehl `php artisan db:seed --class=AccountingInvoiceClearingAccountSeeder --force` legt das bestätigte Konto 1370 nur an, wenn es fehlt. Bestehende Namen, Aktivierung und Kontoart bleiben unverändert; eine abweichende bestehende Nutzung muss vor dem Export geklärt werden.
4. In der Portal-`.env` `ACCOUNTING_LEXWARE_CLEARING_ACCOUNT=1370` setzen, anschließend `php artisan config:clear`.
5. Vorschau neu erstellen und prüfen: je Rechnung nur eine Kreditorenzeile mit voller Rechnungssumme und Text nur Rechnungsnummer; die Aufteilung bleibt detailliert beschrieben. Eine kleine Datei in einen Teststapel der konkreten Lexware-Installation importieren und Kreditor-OP, Steuer und Verrechnungssaldo prüfen.

Ohne konfiguriertes/aktives Konto blockiert der neue Export mit einem konkreten Hinweis. Bereits gespeicherte Exporte lassen sich auch ohne neue Konfiguration weiter herunterladen. Pfand 1141, Vorsteuer 1461, Shift4 1462, Kasse 1600, Bank 1800, Kreditorenkonten und die verwendeten Aufwandskonten werden als Verrechnungskonto abgewiesen. Standard und `.env.example` verwenden das bestätigte Konto 1370. Ein bereits vorhandener leerer oder abweichender `.env`-Wert überschreibt den Standard und muss angepasst werden.

Tests: `php artisan test --filter='AccountingBookingExportTest|AccountingDeleteTest|AccountingImportTest|GatoImportTest|HandelshofImportTest'`.
