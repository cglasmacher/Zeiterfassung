# Schichtübergabe und Tagesabschluss automatisch auf QNAP

Dieser zusätzliche n8n-Workflow ruft bestätigte Schichtübergaben und freigegebene Tagesabschlüsse von der Plattform ab. Er läuft neben dem IONOS-Z-Bericht-Mailworkflow; er liest kein Postfach.

## Zielnamen

Im selben Monatsordner wie die Z-Berichte, z. B. `Buchhaltung/Kassendoku/2026/10/`:

- Erste bestätigte Übergabe am 16.10.2026: `2026.10.16-Schicht.pdf`
- Weitere bestätigte Übergaben desselben Geschäftstags: `2026.10.16-Schicht-02.pdf`, `2026.10.16-Schicht-03.pdf` usw.
- Freigegebener Tagesabschluss: `2026.10.16-Tag.pdf`

Das Datum ist immer der gespeicherte Geschäftstag, unabhängig vom Zeitpunkt der Übertragung. Mehrere Übergaben überschreiben einander nicht. Die Reihenfolge wird stabil aus den bestätigten Übergaben gebildet. Der erste Dateiname wird nicht später umbenannt.

## Plattform einrichten

1. Änderungen übernehmen, Datenbank sichern und auf Plesk bereitstellen.
2. `php artisan migrate --force` ausführen (Tabelle für Ablagebestätigungen). Keine Änderungen an bestehenden Abrechnungsbeträgen; kein neuer Frontend-Build für diesen PR nötig.
3. Mit `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` einen eigenen Export-Token erstellen. Nur auf dem Server in `.env` setzen:

   ```dotenv
   CASH_DOCUMENT_EXPORT_TOKEN=HIER_DAS_GENERIERTE_GEHEIMNIS
   ```

4. `php artisan optimize:clear` ausführen. Der neue Token dient nur dem Abruf dieser PDFs und deren Ablagebestätigung, nicht dem Zugriff auf andere APIs. Den bereits vorhandenen Z-Bericht-Import-Token unverändert lassen.

## n8n einrichten

1. `workflows/n8n/Hemingway-Schicht-Tag-QNAP.json` importieren.
2. In **Konfiguration** eure Plattform-HTTPS-Adresse einsetzen. QNAP-Basispfad ist `/share/Buchhaltung/Kassendoku`; denselben tatsächlich per SFTP erreichbaren Basispfad wie beim Z-Bericht-Workflow verwenden. Mit **FTP → SFTP → List** prüfen, falls noch nicht geschehen.
3. Einen **HTTP Header Auth**-Zugang erstellen: Name `Authorization`, Wert `Bearer ` gefolgt vom neuen `CASH_DOCUMENT_EXPORT_TOKEN`. Diesen Zugang in allen drei HTTP-Knoten auswählen: **Offene PDFs abrufen**, **PDF herunterladen**, **Ablage bestaetigen**.
4. Im Knoten **Auf QNAP ablegen** den vorhandenen SFTP-Zugang für `vpn.hemingway-hilden.de`, Port `4322`, auswählen. Er benötigt Schreibrecht einschließlich Unterordneranlage.
5. Workflow manuell testen und beide Dateitypen im QNAP File Station prüfen. Danach aktivieren/veröffentlichen. Der Zeitplan läuft alle zwei Minuten und verarbeitet bis zu zehn Übergaben und zehn Tagesabschlüsse pro Lauf.

Die JSON enthält keine Passwörter/Tokens und ist beim Import inaktiv. Ein echter Verbindungstest erfordert die auf eurem IONOS-Server hinterlegten Credentials. Es wird kein zusätzlicher Webhook und kein Plattform-Queue-Worker benötigt.

## Verhalten und Wiederholungen

- Schicht-PDFs entstehen aus den gespeicherten bestätigten Übergaben, mit Bedienerwerten, Bargeldrechnung, Ausgaben und Mitarbeiterstunden/Auszahlungen. Der Tagesabschluss verwendet dieselbe gespeicherte Abschlussvorlage wie der vorhandene PDF-Download.
- Entwürfe des Tagesabschlusses erscheinen nicht im Export. Bereits bestätigte historische Vorgänge werden beim ersten Lauf schrittweise nachgeholt; vor dem ersten Aktivieren den historischen Umfang beachten.
- Erst ein erfolgreicher SFTP-Upload führt zur API-Ablagebestätigung. Bei Download-/SFTP-Fehlern bleibt der Datensatz offen und wird beim nächsten Zeitplan erneut angeboten. Es ist keine manuelle erneute Bestätigung der Abrechnung nötig.
- Uploads und Bestätigungen dürfen wiederholt werden. Stabile Dateinamen vermeiden zusätzliche Dateien. Nach einem abgebrochenen SFTP-Upload kann zeitweise eine unvollständige Datei liegen; der erfolgreiche Wiederholungsupload ersetzt sie.
- Bereits bestätigte Dateien werden nicht bei jedem Lauf erneut übertragen. Die Datenbanktabelle `cash_document_archives` hält Dokumentenschlüssel, SFTP-Pfad und Bestätigungszeitpunkt fest. Manuelles Löschen einer Datei auf der QNAP wird nicht automatisch erkannt; dies ist ein Export, kein bidirektionaler Backup-Abgleich.
- Die Buchungs-/n8n-Übergabe des Tagesabschlusses bleibt unabhängig. Eine PDF-Ablage bestätigt keinen Buchungsimport.
- Gutscheinbehandlung und vereinfachte Abrechnungsfelder bleiben wie besprochen in der Planung und werden nicht verändert.

## Endpunkte

Alle folgenden Endpunkte benötigen den separaten Bearer-Token und sind auf 120 Anfragen/Minute/IP begrenzt:

- `GET /api/integrations/cash-documents`: noch nicht bestätigte Dokumente.
- `GET /api/integrations/cash-documents/{handover|day}/{id}/pdf`: gespeicherten Vorgang als PDF rendern.
- `POST /api/integrations/cash-documents/{handover|day}/{id}/ack`: JSON `{ "remote_path": "/share/Buchhaltung/Kassendoku/2026/10/2026.10.16-Tag.pdf" }`, erst nach erfolgreichem Upload.
