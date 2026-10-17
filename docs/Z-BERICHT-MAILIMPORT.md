# Vectron-Z-Berichte aus IONOS über n8n

## Einrichtung

1. Aktuellen Pull Request übernehmen und in Plesk bereitstellen. Vorher Datenbank sichern, danach `php artisan migrate --force`, Frontend neu bauen und `php artisan optimize:clear` ausführen. Alternativ die vorhandene Deployment-Routine verwenden.
2. Ein eigenes IONOS-Postfach für die Z-Berichte verwenden. In Vectron diese Empfängeradresse hinterlegen bzw. zunächst einen Bericht manuell dorthin versenden. Ob Vectron automatisch sendet, muss an einer echten Mail geprüft werden.
3. Ein Geheimnis mit `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` erzeugen und ausschließlich auf dem Server in `.env` hinterlegen:

   ```dotenv
   CASH_Z_REPORT_IMPORT_TOKEN=HIER_DAS_GENERIERTE_GEHEIMNIS
   CASH_BUSINESS_DAY_START_HOUR=6
   ```

   Anschließend `php artisan config:clear`. Token nicht in Git oder die Workflow-JSON schreiben.
4. `workflows/n8n/IONOS-Vectron-Z-Bericht.json` in n8n importieren.
5. Im Knoten **IONOS Posteingang** IMAP-Zugangsdaten auswählen: Host `imap.ionos.de`, Port `993`, SSL/TLS aktiv, Benutzername = vollständige E-Mail-Adresse, Passwort des Postfachs. TLS-Prüfung aktiviert lassen. Quelle: https://www.ionos.de/hilfe/e-mail/allgemeine-themen/serverinformationen-fuer-imap-pop3-und-smtp/
6. Im Knoten **Konfiguration** `platformUrl` auf die echte HTTPS-Adresse der Zeiterfassung setzen. Optional die tatsächlich beobachtete Absenderadresse in `allowedSender` eintragen; bei leerem Wert werden alle PDF-Anhänge dieses dedizierten Postfachs verarbeitet.
7. Im letzten HTTP-Knoten einen **Header Auth**-Zugang auswählen: Name `Authorization`, Wert `Bearer ` gefolgt vom identischen Import-Token. In der JSON sind keine Credentials enthalten.
8. Workflow testweise starten, Z-Bericht an das Postfach senden und in n8n die erfolgreiche HTTP-Antwort prüfen (`201`, bei identischem PDF `200` mit `duplicate: true`). Erst danach aktivieren/veröffentlichen.
9. In der Plattform **Abrechnung** öffnen und den passenden Geschäftstag wählen. Auch die Schichtübergabe zeigt den Eingang. Die Meldung lautet **„Z-Bericht für diesen Tag verfügbar“**; das Original lässt sich öffnen.

## Tageszuordnung

Der Server liest ausschließlich Datum/Uhrzeit im Kassenfuß des bekannten Vectron-Layouts `Finanzbericht <Z1>`. Weder Empfangsdatum der Mail noch der Dateiname noch „Letzte Nullst.“ werden als Geschäftstag verwendet. Wiederholte identische Fußzeilen sind zulässig; unterschiedliche Zeitpunkte führen zu einer offenen Zuordnung. Unbekanntes Layout, gescannte PDFs oder fehlender PDF-Text bleiben ebenfalls offen und sichtbar.

Standard: Ein Bericht mit Kassenzeit vor 06:00 Uhr **Europe/Berlin** wird dem Vortag zugeordnet. Beispiel: 18.10.2026, 01:20 → Geschäftstag 17.10.2026. Diese Grenze entspricht der bisherigen Planung des Tagesabschluss-Zeitraums und ist mit `CASH_BUSINESS_DAY_START_HOUR` (0–23) änderbar. Bei abweichenden Öffnungszeiten anpassen. Ein Z-Bericht kann mehrere Tage abdecken; die automatische Zuordnung ist deshalb ein Vorschlag nach Berichtszeit, keine Bestätigung seines Umsatzzeitraums.

Unter **Letzte Eingänge und Tageszuordnung korrigieren** können Mitarbeiter die Zuordnung nach Prüfung des Original-PDFs ändern. Nicht zugeordnete PDFs werden separat angezeigt. Die Änderung wird mit Benutzer, vorherigem und neuem Datum protokolliert. Sie ändert weder bestehende Abschlüsse noch Umsätze, Auszahlungen oder n8n-Buchungen.

Die Meldung aktualisiert sich beim Öffnen und alle 60 Sekunden; **Prüfen** aktualisiert sofort. Sie erscheint auch, wenn das PDF vor Anlage des Geschäftstags angekommen ist. Ohne eröffneten Tag gilt das gewählte Datum im Feld „Neuer Geschäftstag“.

## Sicherheit, Dubletten und Fehler

- Eingang: `POST /api/integrations/vectron/z-reports`, Bearer-Token erforderlich, 30 Anfragen/Minute/IP. Ohne konfigurierten Token bleibt der Eingang deaktiviert. Der Token erlaubt nur Dokumenteneingang, keinen Download und keine Geldbuchung.
- Multipart-Felder: `file` (PDF, maximal 10 MiB), optional `extracted_text` (maximal 1 Mio. Zeichen), `message_id`.
- Originaldatei wird auf dem privaten Laravel-Datenträger gespeichert. PDF-Aufruf und Zuordnung erfordern eine angemeldete Sitzung. Kein `storage:link` erforderlich.
- SHA-256 des PDF-Inhalts ist eindeutig. Wiederholungen und HTTP-Retries erzeugen keinen zweiten Datensatz. Gleiche Berichtsnummer bei anderem PDF-Inhalt wird nicht still überschrieben; beide Originale bleiben prüfbar.
- Datenbank und `storage/app/private/cash/z-reports` gemeinsam sichern. Mehrere App-Server benötigen einen gemeinsamen privaten Datenträger und Cache mit Locks.
- Der Workflow verarbeitet mehrere PDFs als einzelne Items und behält ihre Binärdaten auch bei fehlgeschlagener Textextraktion. Die Textauslese ist kein OCR. Ein fehlgeschlagener Upload wird dreimal versucht und danach als Fehler angezeigt.
- Der IMAP-Trigger markiert die Mail schon bei Empfang als gelesen. Nach einem Fehler die gespeicherte fehlgeschlagene Ausführung in n8n erneut ausführen; nicht auf einen automatischen erneuten Mailabruf verlassen. Originalmail nicht löschen. Nicht mehrfach parallel manuell starten. Die n8n-Aufbewahrung fehlgeschlagener Ausführungen samt Binärdaten ausreichend einstellen.
- Erst neue Mails nach Aktivierung testen; zum Wiederholen eine neue Mail senden oder die gespeicherte Ausführung wiederholen. Die JSON ist beim Import absichtlich inaktiv und benötigt die beiden Zugangsdaten sowie eure Plattformadresse.

Diese Version übernimmt **Original-PDF und Verfügbarkeitsanzeige**, noch keine automatischen Steuer-/Zahlungswerte. Beträge aus dem Original weiterhin prüfen und wie bisher im Tagesabschluss eintragen.

## Technische Referenzen

- IMAP-Trigger: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.emailimap/
- PDF-Auslese: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.extractfromfile/
- Geprüfte Node-Schemas aus dem offiziellen n8n-Repository: `EmailReadImap/v2/EmailReadImapV2.node.ts` und `Files/ExtractFromFile/actions/pdf.operation.ts` (Keep Source = both).
