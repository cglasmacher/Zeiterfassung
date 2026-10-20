# Vectron-Z-Berichte aus IONOS über n8n

## Einrichtung

1. Aktuellen Pull Request übernehmen und in Plesk bereitstellen. Vorher Datenbank sichern, danach `php artisan migrate --force`, Frontend neu bauen und `php artisan optimize:clear` ausführen. Alternativ die vorhandene Deployment-Routine verwenden.
2. Ein eigenes IONOS-Postfach für die Z-Berichte verwenden. In Vectron diese Empfängeradresse hinterlegen bzw. zunächst einen Bericht manuell dorthin versenden. Ob Vectron automatisch sendet, muss an einer echten Mail geprüft werden.
3. Ein Geheimnis mit `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` erzeugen und ausschließlich auf dem Server in `.env` hinterlegen:

   ```dotenv
   CASH_Z_REPORT_IMPORT_TOKEN=HIER_DAS_GENERIERTE_GEHEIMNIS
   CASH_Z_REPORT_PREVIOUS_DAY_UNTIL=11:00
   ```

   Anschließend `php artisan config:clear`. Token nicht in Git oder die Workflow-JSON schreiben.
4. `workflows/n8n/IONOS-Vectron-Z-Bericht.json` in n8n importieren.
5. Im Knoten **IONOS Posteingang** IMAP-Zugangsdaten auswählen: Host `imap.ionos.de`, Port `993`, SSL/TLS aktiv, Benutzername = vollständige E-Mail-Adresse, Passwort des Postfachs. TLS-Prüfung aktiviert lassen. Quelle: https://www.ionos.de/hilfe/e-mail/allgemeine-themen/serverinformationen-fuer-imap-pop3-und-smtp/
6. Im Knoten **Konfiguration** `platformUrl` auf die echte HTTPS-Adresse der Zeiterfassung setzen. Optional die tatsächlich beobachtete Absenderadresse in `allowedSender` eintragen; bei leerem Wert werden alle PDF-Anhänge dieses dedizierten Postfachs verarbeitet.
7. Im letzten HTTP-Knoten einen **Header Auth**-Zugang auswählen: Name `Authorization`, Wert `Bearer ` gefolgt vom identischen Import-Token. In der JSON sind keine Credentials enthalten.
8. Workflow testweise starten, Z-Bericht an das Postfach senden und in n8n die erfolgreiche HTTP-Antwort prüfen (`201`, bei identischem PDF `200` mit `duplicate: true`). Erst danach aktivieren/veröffentlichen.
9. In der Plattform **Abrechnung** öffnen und den passenden Geschäftstag wählen. Auch die Schichtübergabe zeigt den Eingang. Die Meldung lautet **„Z-Bericht für diesen Tag verfügbar“**; das Original lässt sich öffnen.

## Tageszuordnung

### Wenn n8n Seiten erkennt, aber `text: ""` liefert

Die Plattform liest das hochgeladene Original selbst mit Poppler `pdftotext`, wenn `extracted_text` fehlt, leer ist oder nur Leerzeichen enthält. Der bestehende n8n-Workflow bleibt unverändert; das PDF wird bereits mitgesendet.

Auf dem **Plesk-Anwendungsserver** (Ubuntu/Debian) einmalig als Administrator installieren:

```bash
sudo apt-get update
sudo apt-get install -y poppler-utils
/usr/bin/pdftotext -v
```

Danach den neuen Plattformstand deployen und dieselbe n8n-Ausführung ab dem Plattform-HTTP-Aufruf erneut ausführen. Standardpfad ist `/usr/bin/pdftotext`; bei abweichendem Pfad `CASH_Z_REPORT_PDFTOTEXT_BINARY` in `.env` setzen und `php artisan config:clear` ausführen. PHP-FPM muss `proc_open` erlauben und das Programm ausführen dürfen. Es wird kein externer OCR-Dienst verwendet. Die Extraktion ist auf 20 Sekunden, die ersten 20 Seiten und 1 Mio. Ergebniszeichen begrenzt. Fehlende Software oder Ausführungsfehler werden im Laravel-Log gemeldet; das Original bleibt trotzdem gespeichert und zur manuellen Zuordnung verfügbar. Gescannte Bild-PDFs benötigen weiterhin eine manuelle Tageszuordnung.

Am vorhandenen Vectron-Beispiel liefert Poppler die Berichtsnummer `291` und den Erstellungszeitpunkt `16.10.26 23:21`; die erwartete Antwort enthält `report_id: "291"`, `business_date: "2026-10-16"` und `archive_filename: "Z-Bericht_2026-10-16_Z-291.pdf"`.


Der Server liest Datum/Uhrzeit **unten in der Finanzbericht-Zusammenfassung**. Weder Empfangsdatum der Mail noch der Dateiname noch „Letzte Nullst.“ oben werden als Geschäftstag verwendet. Spätere Bedienerberichte werden für diese Entscheidung ignoriert. Die Erkennung toleriert Zeilenumbrüche, Seitenumbrüche, geschützte Leerzeichen und in eine Zeile zusammengezogene PDF-Felder. Unbekannte Layouts oder gescannte PDFs ohne Text bleiben zur manuellen Zuordnung offen.

**Bis einschließlich 11:00:00 Uhr Europe/Berlin gehört der Bericht zum Vortag.** Beispiele: 20.10.2026 09:15 → 19.10.2026; 20.10.2026 11:00 → 19.10.2026; ab 11:00:01 → 20.10.2026. Die neue Einstellung `CASH_Z_REPORT_PREVIOUS_DAY_UNTIL=11:00` gilt ausdrücklich für diese Z-Berichtszuordnung. Die alte Einstellung `CASH_BUSINESS_DAY_START_HOUR=6` wird hier nicht mehr ausgewertet und kann aus `.env` entfernt werden. Bestehende Kassen-/Abschlusszeiträume werden nicht geändert.

Die **Z-Berichtsnummer** wird als sichtbare Berichts-ID und als `report_id`/`report_number` an n8n geliefert. Der technische Datensatzschlüssel (`id`, zusätzlich `record_id`) bleibt für vorhandene Links und APIs erhalten, weil verschiedene Kassen dieselbe Z-Nummer verwenden können. Dateiname z. B. `Z-Bericht_2026-10-19_Z-291.pdf`. Bei einem weiteren, inhaltlich abweichenden Original mit derselben Z-Nummer und demselben Geschäftstag wird `_Beleg-<Dateikennung>` ergänzt, damit nichts überschrieben wird. Ohne lesbare Z-Nummer wird eine Dateikennung verwendet, keine erfundene Berichtsnummer.

**Bereits unzugeordnete Berichte:** Nach dem Update dieselbe Mail/PDF in n8n erneut ab der PDF-Auslese übermitteln. Die Plattform ergänzt erkannte Daten am vorhandenen Datensatz; es entsteht kein doppeltes PDF. Auch automatisch zugeordnete Berichte können so mit der neuen 11-Uhr-Regel korrigiert werden. Manuelle Tageszuordnungen werden niemals überschrieben. Frühere QNAP-Ablagen werden nicht automatisch verschoben oder umbenannt.

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

## Zusätzliche QNAP-Ablage per SFTP

Alternativer vollständiger Workflow: `workflows/n8n/IONOS-Vectron-Z-Bericht-QNAP.json`.
Er ersetzt den bisherigen Postfach-Workflow; nicht beide gleichzeitig aktivieren.
Die Abrechnungsoberfläche und Gutscheinlogik werden dabei nicht verändert.

1. JSON importieren und die bisherigen IONOS- und Plattform-Zugangsdaten auswählen. Eure Plattformadresse im Knoten **Konfiguration** eintragen.
2. Im Knoten **Auf QNAP speichern** SFTP-Zugang auswählen/anlegen:
   - Host: `vpn.hemingway-hilden.de`
   - Port: `4322`
   - Benutzer und Passwort/SSH-Schlüssel des berechtigten QNAP-Kontos.
3. Vor dem ersten Upload mit einem temporären **FTP**-Knoten, Protokoll **SFTP**, Operation **List**, den Pfad `/share/Buchhaltung` prüfen. Der Freigabename `Buchhaltung` ist nicht automatisch der vollständige SFTP-Pfad; bei QNAP liegt er üblicherweise unter `/share`. Falls die Freigabe im SFTP-Stamm anders eingeblendet wird, den tatsächlich sichtbaren absoluten Basispfad in **Konfiguration → qnapBasePath** eintragen. Die Standardeinstellung ist `/share/Buchhaltung/Kassendoku`.
4. Mit einer neuen echten Mail testen. Der vollständige Ablauf muss bis zum letzten SFTP-Knoten erfolgreich sein; danach die Datei im QNAP File Station prüfen und erst anschließend den alten Workflow deaktivieren und den neuen aktivieren.

Beispiel für Geschäftstag 16.10.2026 und Z-Berichtsnummer 291:

`Buchhaltung/Kassendoku/2026/10/Z-Bericht_2026-10-16_Z-291.pdf`

Jahr/Monat stammen aus `business_date` der erfolgreichen Plattformantwort. Auch bei Eingang im November landet ein Oktober-Geschäftstag im Oktober. Der aktualisierte Workflow verwendet `archive_filename` aus der Plattformantwort und damit die Z-Berichtsnummer statt der internen Datenbank-ID. Wiederholungen verwenden denselben Dateinamen. PDF-Binärdaten werden nach der HTTP-Antwort über die n8n-Item-Verknüpfung wieder mitgegeben; mehrere Anhänge werden nicht vermischt.

Der SFTP-Upload-Knoten legt fehlende Jahres-/Monatsordner automatisch an, wenn das Konto dafür Schreibrechte besitzt. Berichte ohne zugeordneten Geschäftstag landen in `Kassendoku/Unzugeordnet` statt in einem geratenen Monat. Eine spätere manuelle Zuordnung in der Plattform verschiebt die bereits archivierte Datei nicht automatisch; dann auf der QNAP ebenfalls verschieben.

Wenn die Plattform erfolgreich ist, die QNAP aber nicht erreichbar ist, bleibt der Workflow fehlgeschlagen. Nach Behebung dieselbe gespeicherte Ausführung wiederholen. Bei einem abgebrochenen Upload kann eine unvollständige Datei vorhanden sein; ein erfolgreicher Wiederholungsupload ersetzt sie. Nachträgliche Änderungen an der Tageszuordnung sind kein bidirektionaler Archiv-Sync.

Die Portweiterleitung sollte wie geplant auf die öffentliche IONOS-Server-IP begrenzt sein. Die Workflow-JSON enthält keine Passwörter und keine automatisch gebundenen Zugangsdaten. Ein echter Verbindungstest ist nur aus dem freigegebenen IONOS-Netz mit den dortigen Credentials möglich.

Quelle für Node-Schema und automatische Unterordneranlage: offizielles n8n-Repository, `packages/nodes-base/nodes/Ftp/Ftp.node.ts` (`recursivelyCreateSftpDirs` vor SFTP-Upload).
