# Lexware Financial Office Plus: Buchungsstapel

## Portal

1. Belege prüfen und freigeben.
2. Unter Buchhaltung im Bereich „Lexware-Buchungsstapel“ das Belegjahr wählen und „Export vorbereiten“ anklicken.
3. Die Vorschau umfasst alle freigegebenen, noch nicht exportierten Belege dieses Kalenderjahres. Betrag, Soll-/Habenkonten und Steuerschlüssel prüfen.
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

### Pfand

1141 wird ausschließlich netto gegen den Kreditor gebucht. Die Vorsteuer wird als separater Satz 1461 gegen denselben Kreditor gebucht. Beide Sätze haben einen leeren Steuerschlüssel: Dafür darf in Lexware keine zusätzliche automatische Steuer entstehen. Die Lexware-Konten 1141 und 1461 müssen entsprechend eingerichtet sein. Negative Beträge werden als positive Buchungsbeträge mit vertauschtem Soll/Haben ausgegeben.

Die bekannte Testrechnung ergibt sechs CSV-Sätze: vier Wareneinkaufsgruppen und zwei Pfandsätze. Für die Pfandrückgabe stehen 2,38 € Soll 70007 / Haben 1141 sowie 0,45 € Soll 70007 / Haben 1461 in der Datei. Der gesamte Kreditorensaldo beträgt 324,81 € Haben. Vor Übernahme des ersten Stapels diese Werte in Lexware abgleichen; ein realer Lexware-Import konnte in der Entwicklungsumgebung nicht ausgeführt werden.

Belegbilder anschließend bei Bedarf manuell in Lexware verknüpfen. Ein erneuter Import derselben CSV in Lexware kann dort Doppelbuchungen erzeugen; der Portal-Schutz verhindert nur einen erneuten Export derselben Belegdatensätze. Inhaltsgleiche Rechnungen aus unterschiedlichen E-Mails benötigen weiterhin die fachliche Dublettenprüfung.

## Nachweise und Grenzen

- Offizielle Feldformate und Schlüssel für buchhaltung/plus: https://tsp.haufe-lexware.com/tsp_wm_get_attachment.php?att=c676753036_Lexware-buchhaltung-Schnittstellenbeschreibung-ASCII.pdf
- Offizielles Lexware-Handbuch zum Importassistenten und Steuerberechnung bei Automatikkonten: https://www.lexware.de/fileadmin/support/handbuecher/2026/handbuch_buchhaltung_berater.pdf (pro/premium; konkrete Plus-Oberfläche vor Ort prüfen).

EUR und die im Portal vorhandenen Steuersätze 0/7/19 % sind unterstützt. Reverse Charge, EU-Sonderfälle, abweichende Wirtschaftsjahre/Leistungsperioden und Fremdwährungen benötigen eigene geprüfte Exportprofile. Der Export übermittelt keine Daten direkt an Lexware und kennt dessen tatsächliche Konteneigenschaften nicht.

## Technik und Deployment

Es werden die vorhandenen Tabellen AccountingExportBatch und AccountingDocument genutzt. Ein Stapel speichert CSV-Bytes als Base64 im vorhandenen JSON-Metadatenfeld, Hash sowie Dokument-/Zeilensnapshots. Statusänderung und Snapshot werden in einer Transaktion vorgenommen; Belege sind dabei gesperrt. Download ist ausschließlich für Portaladministratoren verfügbar und prüft den gespeicherten Hash. Vorschau verändert keine Belege. Keine neuen Tabellen, keine Migration, kein neuer Seeder und keine n8n-Änderung erforderlich.

Deployment: `git pull`, dann `npm run build`.

Tests: `php artisan test --filter='AccountingBookingExportTest|AccountingDeleteTest|AccountingImportTest|GatoImportTest'`.
