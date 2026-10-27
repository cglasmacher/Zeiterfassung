# Gato: deterministische Belegverarbeitung

## Datenfluss

Der inaktive n8n-Importworkflow erkennt Mailabsender der exakten Domain `getraenke-gato.de`. Nach PDF-/Kassenberichtfilter wird der Gato-Zweig ohne GPT verwendet. Er übergibt Original-PDF, Originalnamen, stabile Anhangsreferenz und `extraction_profile=gato_layout_v1` an den bestehenden Portalimport. Andere Absender behalten den bisherigen KI-Zweig. Weitergeleitete Gato-Rechnungen werden nicht anhand einer beliebigen Rechnungstextähnlichkeit automatisch als Gato eingestuft.

Das Portal verlangt den aktiven Kreditor 70007 und extrahiert mit `pdftotext -layout` selbst den Text aus dem hochgeladenen Original. Der mitgelieferte n8n-Text ist keine Grundlage für Geldbeträge. Damit ist die Erkennung unabhängig vom als Grafik eingebetteten Gato-Briefkopf und von der Reihenfolge, in der PDF.js Textstücke ausliest.

## Fachliche Prüfung

- Seitenfolge, einheitliche Rechnungsnummer/-datum, Warenpositionen, Menge × Einzelpreis und Summenblock müssen lesbar sein.
- Sternchen bedeutet 7 %, vollständig gelesene Warenzeile ohne Sternchen 19 %. Bei Widerspruch zum Summenblock scheitert die Analyse.
- Pfand wird ausschließlich aus der Leerguttabelle gebucht. Die Pfandspalte neben Warenpositionen wird nicht nochmals addiert. Nullsaldozeilen bleiben als Belegnachweis gespeichert, erzeugen aber keine Buchungsposition.
- Die Steuergruppe des Pfands wird nur bei eindeutiger Übereinstimmung mit den gedruckten Steuergruppen übernommen. Gemischte bzw. unklare Zuordnung und ein Nullsaldo mit nicht nullwertigen Bewegungen bleiben zur Prüfung offen.
- Netto, Steuer und Brutto werden exakt gegen den Summenblock geprüft. Positionssteuern werden über das größte Restverfahren verteilt, einschließlich negativer Leergutbewegungen; die gedruckte Gruppensteuer bleibt erhalten.
- Lernregeln im vorhandenen System haben Vorrang. Danach folgen feste Gato-Artikel-/Produktregeln. Unter anderem: 11388 → 5132, 80400 → 5131. Unbekannte Konten werden nicht angelegt; unbekannte Artikel bleiben ohne Kontierung, sofern keine eindeutige Produktregel greift.
- Originalzeilen, Sternchen, Pfandbewegungen, Steuergruppen und Rundungsmethode stehen unter `raw_payload.gato`. Die Review-Oberfläche zeigt den angewendeten Regelschlüssel. Die Freigabe prüft auch die gespeicherten Steuergruppensummen.

Fehlgeschlagene PDF-/Layoutanalyse erzeugt mit dem Original einen Beleg im Status `analysis_failed`, ohne KI-Ersatzbeträge. Der vorhandene Schutz vor überschreibenden Wiederholungsimporten bleibt erhalten. Eine gezielte Neuanalyse bereits gespeicherter Belege benötigt weiterhin einen eigenen Review-Prozess.

## Abnahme und Grenzen

Die bereitgestellte Originalrechnung ergibt 12 Warenpositionen und acht nicht nullwertige Pfandpositionen: Netto 275,94 €, Steuer 48,87 €, Brutto 324,81 €. Steuergruppen: 7 % mit 29,62 € netto / 2,07 € Steuer; 19 % mit 246,32 € netto / 46,80 € Steuer. Die mitgeführte Textfixture enthält ersetzte Empfänger-/Belegidentifikatoren; das Original-PDF wird nicht im Repository gespeichert.

`php artisan test --filter='AccountingImportTest|GatoImportTest'`

`node --test tests/Workflow/accounting-invoices.test.mjs`

Das bekannte native Gato-Layout ist abgedeckt. Andere Layouts, Scans/OCR, komplexe Gutschriften und weitere Lieferanten werden nicht als verifiziert behauptet. Parser- und Portaltests nutzen das bekannte Layout; ein produktiver Maildurchlauf auf dem n8n-/Plesk-Server ist vor Aktivierung erforderlich.

## Deployment

1. Portaländerungen ausrollen, vorhandene Konten und Kreditor 70007 prüfen. Dieser Schritt benötigt keine neue Migration.
2. Auf Plesk muss PHP den Befehl `/usr/bin/pdftotext -v` ausführen können. Optional `ACCOUNTING_PDFTOTEXT_BINARY` auf den installierten Pfad setzen. Ohne verfügbaren Befehl bleibt die Analyse sichtbar fehlgeschlagen.
3. Frontend mit `npm install` und `npm run build` bauen; Laravel-Konfigurationscache nach Umgebungsänderungen erneuern.
4. Aktualisierte Workflow-JSON importieren. IMAP-/OpenAI-Zugangsdaten auswählen und den Platzhalter im HTTP-Authorization-Header ersetzen. Alten Rechnungsworkflow deaktivieren, bevor der neue aktiv wird.
5. Mit einer neuen Gato-Mail testen: Gato-Zweig ohne GPT, 324,81 € brutto, Pfand −2,38 €, vollständiges Original im Review. Wiederholung derselben Referenz darf den Beleg nicht verändern. Alte Mails nicht pauschal erneut als ungelesen markieren.
