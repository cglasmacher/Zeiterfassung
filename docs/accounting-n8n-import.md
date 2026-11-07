# Eingangsrechnungen: Polling statt Listener

Basis ist der bereitgestellte funktionierende Z-Workflow „Hemingway - IONOS Z-Berichte zur Plattform und QNAP - POLLING“. Der produktive n8n-Server ist hier nicht direkt zugänglich.

## Einrichtung

1. Portaländerungen ausrollen. `pdftotext`, `pdfimages` und für Handelshof `pdfdetach` müssen ausführbar sein (Paket `poppler-utils`).
2. Alten Rechnungsworkflow sichern und deaktivieren. `workflows/n8n/Hemingway-Eingangsrechnungen.json` als inaktiven Workflow importieren.
3. Bei den drei Community-IMAP-Nodes (Suchen, Anhänge laden, Archivieren) dasselbe bestehende IMAP-Konto wie beim funktionierenden Z-Poller auswählen. Node: `n8n-nodes-imap.imap`, Version 1, Authentifizierung `coreImapAccount`. Im Downloadnode müssen **All Attachments** und **Include Inline Attachments** aktiv sein. Sichtbare PDFs können insbesondere bei Weiterleitungen als Inline-MIME-Teil vorliegen, auch wenn `attachmentsInfo` in der Suchantwort leer ist. Die nachgelagerte Auswahl verarbeitet weiterhin nur PDFs. Kein zusätzlicher Listener und keine neue IMAP-Bibliothek erforderlich.
4. Den Ordner `INBOX/Eingangsrechnungen` im Postfach anlegen und im Archivierungsnode auswählen. Falls der Anbieter einen anderen internen Ordnerpfad meldet, den vorhandenen Ordner aus der Liste wählen.
5. Vorhandene OpenAI-Zugangsdaten auswählen. In beiden HTTP-Nodes (Lieferantenerkennung und Belegübergabe) denselben `ACCOUNTING_IMPORT_TOKEN` eintragen. Die Importdatei enthält Platzhalter statt Geheimnissen.
6. Mit einer neuen Mail und zwei PDF-Rechnungen testen, bevor der Zeitplan aktiviert wird. Danach erfolgt der Abruf alle zwei Minuten.

## Ablauf und Wiederholungen

Zeitplan → ungelesene Mails in INBOX suchen → direkte Vectron-Mails ausschließen → Anhänge laden → jede PDF einzeln → Kassenberichtfilter → PDF-Lieferantenerkennung → Gato / Handelshof / ISB ohne GPT oder bisheriger KI-Zweig → Portalbestätigung → vollständige Mails nach `INBOX/Eingangsrechnungen` verschieben.

Handelshof: Der neue Zweig „Handelshof ohne KI?“ liegt am Nein-Ausgang von „Gato ohne KI?“. Bei erkanntem Profil geht das Original direkt zum Portal; dort werden die eingebettete `factur-x.xml` und der sichtbare PDF-Text unabhängig von n8n ausgelesen und abgeglichen. Auch ohne eingebettetes XML bleibt ein erkannter Handelshof-Beleg in diesem Zweig und erhält im Portal einen sichtbaren Analysefehler statt erfundener KI-Beträge. Details: [Handelshof-Profil](accounting-handelshof.md).

IMAP-Such-/Downloadparameter stammen aus der funktionierenden Z-Vorlage. Der Rechnungsworkflow wählt keine Lieferanten anhand des Weiterleitungsabsenders aus. Direkte Mails von `noreply@myvectron.com` werden vor dem Download ausgeschlossen; weitergeleitete erkennbare Kassenbericht-PDFs werden vor GPT übersprungen.

Die Mail bleibt bis zur vollständigen Bestätigung im Posteingang. Nur eine Portalantwort mit positiver Beleg-ID und gespeichertem Original-PDF zählt als Annahme; ein bereits gespeicherter Dublettenbeleg zählt ebenfalls. Auch zur Prüfung oder als `analysis_failed` gespeicherte Originale zählen als im Portal gesichert.

Pro Mail müssen alle erwarteten PDF-Anhänge angenommen oder als Kassenbericht übersprungen sein. Mindestens eine Rechnung muss angenommen sein. Doppelte oder fehlende Anhangsergebnisse verhindern das Verschieben. Mails ohne PDF und reine Kassenbericht-Mails werden vom Rechnungsworkflow nicht archiviert.

PDF-/KI-/HTTP-Fehler erzeugen `status: failed` im Node „PDF-Ergebnis prüfen“ und lassen die Schleife mit der nächsten PDF fortfahren. Andere vollständig bearbeitete Mails dürfen trotzdem archiviert werden. Die betroffene Mail bleibt für den nächsten Poll im Posteingang; bereits angenommene Anhänge werden durch den idempotenten Portalimport nicht überschrieben. Fehler sind im jeweiligen Fehlerausgang und in „PDF-Ergebnis prüfen“ sichtbar, auch wenn die Gesamtausführung durch die Fehlerbehandlung erfolgreich endet.

Der letzte IMAP-Node verschiebt die Mail wie in der Z-Vorlage. Er setzt nicht lediglich das Gelesen-Flag. Der Z-Workflow selbst wird nicht geändert.

## Tests und Grenzen

`node --test tests/Workflow/accounting-invoices.test.mjs`

Die Tests führen den eingebetteten JavaScript-Code mit n8n-Testdoubles aus. Abgedeckt sind Community-IMAP-Metadaten, Anhangszahl, Originalzuordnung, Vectron-Ausschluss, unterschiedliche Weiterleitungsabsender, Dublettenantworten und Teilfehler. Der echte Community-Node und die Schleifenverknüpfung müssen auf dem n8n-Server mit Testmails bestätigt werden.

Nicht mehrere Rechnungsworkflows gleichzeitig aktivieren. Alte Mails nicht pauschal als ungelesen markieren. Bei einem Archivierungsfehler den IMAP-Ordner prüfen; die Originale sind dann bereits im Portal gesichert. Inhaltliche Dubletten mit unterschiedlichen Message-IDs und OCR bleiben separate Aufgaben.

ISB: Nach dem Handelshof-Zweig prüft „ISB ohne KI?“ das Profil `isb_layout_v1`. Das Original geht unverändert zum Portal; Laravel liest Positionen und Steuer aus dem PDF. Details: [ISB-Profil](accounting-isb.md).
