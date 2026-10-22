# Hemingway Infosystem

## Integration in den Bestand

Ein Codebestand, eine Datenbank: Laravel 12, React 18/Inertia und Vite. Die vorhandene Sitzung und CSRF-Prüfung schützen die Verwaltung unter `/infosystem`; die Navigation enthält den Eintrag **Betrieb → Infosystem**. Zeiterfassung, RFID-Endpunkte, Dienstplan, Kasse, Gutscheine, Belegimport und Exporte bleiben in ihren bestehenden Modulen. Der TV liest den heutigen Dienstplan einschließlich Standardzeiten aus den Schichttypen. Es werden keine Löhne, Kontaktdaten oder RFID-Kennungen an den TV geliefert.

Neue Tabellen: `info_displays`, `info_items`, `info_integrations`, `info_reservations`; neues Berechtigungsfeld `users.is_info_admin`. Die Migration übernimmt vorhandene Verwaltungskonten als Infosystem-Administratoren. Neu registrierte Konten erhalten diese Berechtigung nicht. Bei Bedarf: `php artisan info:admin name@example.com` bzw. `--revoke`. Die nachfolgende Workspace-Migration ergänzt rollenbasierte Rechte für die gesamte Verwaltung (siehe unten).

## Bedienung

- Mitteilungen: Text, Priorität, Veröffentlichungszeitraum, alle oder ein bestimmtes Display.
- Aufgaben: zusätzlich Mitarbeiterzuweisung, Fälligkeit, erledigen/wieder öffnen. Erledigte Aufgaben verschwinden vom TV.
- Events: Vorschau für die nächsten 14 Tage; ohne Endzeit gilt eine Dauer von sechs Stunden.
- Medien: YouTube-Video-Links, HTTPS-Bilder/-Videos oder private Datei-Uploads (JPEG/PNG/WebP, MP4/WebM, maximal 100 MB). Reihenfolge und Anzeigedauer sind einstellbar.
- Displays: „Küche“ (`kitchen`) ist vorangelegt. Weitere Displays können erstellt, umbenannt, deaktiviert und auf Dienstplan-Abteilungen beschränkt werden. Gastnamen sind standardmäßig ausgeblendet.
- Unter **Displays → Zugang neu erzeugen** den einmal angezeigten Verbindungslink auf dem TV öffnen. Das verschlüsselte HttpOnly-Cookie verbindet diesen Browser; in der Datenbank liegt nur ein Token-Hash. Erneutes Erzeugen widerruft bisherige Verbindungen.

Der TV zeigt links Medien und Mitteilungen, in der Mitte Reservierungen über Aufgaben und rechts Events über beide Zeilen. Reservierungen werden nach Beginn in Berliner Ortszeit als Gästezahlen für 10–12, 12–17 und 17–21 Uhr zusammengefasst; außerhalb dieser Zeiten gebuchte Gäste werden zusätzlich ausgewiesen.

YouTube direkt am TV: **Video auswählen** öffnen, Suchbegriff eingeben, **Suchen** drücken und ein Video auswählen. Ergebnisse lassen sich mit Pfeiltasten/OK bedienen; weitere Treffer über **Weitere Ergebnisse**. Alternativ einen normalen YouTube-Video-Link in dasselbe Feld einfügen. Die Auswahl bleibt je Display in diesem Browser gespeichert und läuft ohne Zeitlimit; Live-Aktualisierungen und Tonwechsel starten das Video nicht neu. **Pause/Abspielen** steuert den Player. Die Suche pausiert ein laufendes Video, bis sie geschlossen wird. Der Player erhält nur die Origin als Referer, keinen Display-Verbindungstoken.

Einmalig serverseitig `YOUTUBE_API_KEY=...` in der `.env` setzen und danach `php artisan config:cache` ausführen. Im Google-Projekt muss **YouTube Data API v3** aktiviert sein. Der Key wird nur vom Server an Google gesendet; gegebenenfalls auf diese API und die ausgehende Server-IP beschränken, keine HTTP-Referrer-Beschränkung verwenden. Die Suche braucht nur das verbundene Display-Cookie, keinen Verwaltungslogin. Ergebnisse sind auf einbettbare Videos beschränkt, zehn Minuten gecacht und die Suchroute auf zwölf Aufrufe pro Minute/IP begrenzt. Ohne Key ist die direkte Linkauswahl bereits nutzbar. Es ist keine Migration und kein Queue-Worker für YouTube erforderlich.

Die bisherige Medienrotation bleibt über **Medien anzeigen** erreichbar. Dort gelten weiterhin Reihenfolge und Anzeigedauer aus der Verwaltung. Ein dort hinterlegtes YouTube-Video ist auch Startvorschlag für den festen Player, solange am TV noch nichts gewählt wurde.

Die TV-Ansicht hat große Karten und blättert längere Listen automatisch durch. Sie startet stumm; **Ton an** und **Vollbild** benötigen eine Benutzeraktion. YouTube kann Werbung zeigen, Einbettung einzelner Videos verbieten oder auf bestimmten TV-Browsern zusätzliche Interaktion erfordern. Es wird YouTube eingebettet, keine YouTube-App ferngesteuert.

## Bereitstellung auf Plesk

1. Datenbank und privaten Storage sichern, Branch übernehmen; `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`.
2. `php artisan migrate --force`, `php artisan optimize:clear`, anschließend `php artisan config:cache` und bei Bedarf `php artisan route:cache`.
3. `stream.hemingway-hilden.de` als zusätzlichen Host derselben Anwendung mit demselben `public`-Document-Root einrichten. DNS auf den Plesk-Server und ein gültiges TLS-Zertifikat setzen. Keinen zweiten Laravel-Installationsstand anlegen. `APP_URL` bleibt `https://dienstplan.hemingway-hilden.de`; `INFO_STREAM_HOST=stream.hemingway-hilden.de`.
4. Session-Cookies auf den Verwaltungs-Host beschränken (`SESSION_DOMAIN` leer lassen), `SESSION_SECURE_COOKIE=true`. Die Display-Verbindung immer per HTTPS öffnen.
5. Scheduler einmal pro Minute: `php artisan schedule:run`. Dieser führt den regelmäßigen resmio-Abgleich direkt aus. Für Webhook-Jobs bei asynchroner Queue zusätzlich einen Queue-Worker betreiben, z. B. `php artisan queue:work --tries=3 --timeout=180`; Queue-`retry_after` muss über 180 Sekunden liegen. Alternativ für den Sync-Job eine entsprechend konfigurierte separate Queue verwenden. Fehlgeschlagene Jobs überwachen und nach Behebung erneut ausführen.
6. PHP `upload_max_filesize` mindestens 100M, `post_max_size` größer als 100M und gegebenenfalls nginx `client_max_body_size` anpassen. Dateien bleiben im privaten lokalen Storage, werden nur nach Display-Prüfung ausgeliefert und bei Entfernen eines Mediums gelöscht.
7. Für `/display/*/events` nginx-Buffering deaktivieren (`X-Accel-Buffering: no` wird gesetzt), Proxy-Lesezeit mindestens 30 Sekunden. Mehrere PHP-FPM-Worker bereitstellen. Ein Display hält jeweils einen SSE-Request für bis zu 20 Sekunden offen. Ohne funktionierendes SSE lädt die Ansicht alle 15 Sekunden nach. Der eingebaute PHP-Entwicklungsserver mit nur einem Worker ist deshalb kein geeigneter Lasttest.

Auf dem Stream-Host sind Verwaltungs-, Login- und Integrationspfade gesperrt. Die eigentlichen Display-Endpunkte sind auch auf dem Verwaltungs-Host tokenpflichtig nutzbar. Token-Queryparameter des einmaligen Verbindungslinks dürfen nicht in dauerhaft gespeicherten Access-Logs landen; am Proxy redigieren. Danach wird auf eine URL ohne Token weitergeleitet (`no-store`, `no-referrer`).

## resmio: API-Key und Synchronisation

Implementiert anhand der offiziellen Dokumentation: https://documentation.resmio.com/authenticatedapi.html . Basis: `RESMIO_BASE_URL=https://api.resmio.com/v1`.

1. Unter **Infosystem → resmio** Facility-ID `hemingway-hilden` und den **reinen API-Key** aus resmio speichern. Kein Bearer-Präfix erforderlich. Der Key bleibt verschlüsselt im bisherigen Datenbankfeld `authorization`; es ist keine neue Migration nötig.
2. **Jetzt synchronisieren** starten. Die Anwendung sendet `Api-Key` und `Content-Type: application/json`, liest `/facility/{facility}/bookings` sowie `/facility/{facility}/resources` und verarbeitet alle Seiten. Buchungsfelder sind `id`, `date`, `num`, `name`, `status` und `facility_resources`. Tischreferenzen werden über die Ressourcen in Tischnamen übersetzt. Stornierte Buchungen und No-Shows werden auf dem TV ausgeblendet.
3. Webhook-Ziel: `https://dienstplan.hemingway-hilden.de/api/integrations/resmio/webhook`. Eigene Basic-Auth-Zugangsdaten an beiden Stellen identisch hinterlegen; Aktionen `BOOKING_CREATED`, `BOOKING_UPDATED`, `BOOKING_DELETED`. Diese Einstellungen ändern sich durch die API-Korrektur nicht.
4. Für Dauerbetrieb Scheduler und Queue wie oben einrichten. Regelabgleich alle fünf Minuten; manuell auch `php artisan info:sync-resmio`.

Zeitfilter werden als UTC-Zeitpunkte `date__gte` und `date__lt` für das Berliner Zeitfenster gesendet, auch über den Sommerzeitwechsel hinweg. Die Filter bleiben auf Folgeseiten erhalten. Nur Seiten derselben HTTPS-Origin und desselben Facility-Endpunkts werden akzeptiert. Weiterleitungen werden nicht verfolgt.

Alle Buchungs- und Ressourcenseiten werden vor der atomaren Übernahme validiert. Fehler erhalten den bisherigen Datenbestand und zeigen konkrete Hinweise. `RESMIO_RECONCILE_MISSING=true` aktiviert zusätzlich das Ausblenden von Buchungen, die im vollständigen Snapshot des Zeitfensters fehlen; standardmäßig bleibt diese Option deaktiviert. Lösch-Webhooks und explizite Stornos werden unabhängig davon verarbeitet. Reservierungen älter als sieben Tage werden beim erfolgreichen Abgleich entfernt.

Der Adapter ist gegen dokumentationsgetreue Testantworten geprüft. Ein erfolgreicher Live-Abgleich mit dem im Produktionssystem gespeicherten Schlüssel steht weiterhin aus; der Schlüssel liegt dieser Entwicklungsumgebung nicht vor.

## Tests

`php artisan test` prüft bestehende Features und das Infosystem. Neue Tests decken Verwaltungsrechte, Display-Cookies/Tokenrotation, Aufgabenstatus, Zeit-/Displayfilter, Gastnamen, API-Paginierung, fehlerhafte Antworten, Schutz vor Credential-Weiterleitung, Webhook-Authentifizierung, UTC/Sommerzeitwechsel und private Uploads ab.

`node --test tests/Workflow/*.test.mjs` prüft bestehende n8n-Workflows, YouTube-Linkverarbeitung, gespeicherte TV-Auswahl und die Gästezählung einschließlich Zeitgrenzen und Sommer-/Winterzeit. Die PHP-Tests zur YouTube-Suche prüfen Display-Autorisierung/Rotation, Validierung, API-Filter, Key-Geheimhaltung, Caching, Paginierung und Fehlerantworten. `npm run build` baut die komplette Anwendung mit dem bestehenden Lockfile.

Die TV-Anordnung wird lokal mit Playwright in 1920×1080, 1280×720, 960×540 sowie mobiler Breite geprüft. Die YouTube-Browserprüfung verwendet simulierte Suchantworten und Player-API: Auswahl, Paginierung, Pfeiltasten, Speicherung nach Neuladen, laufende Feed-Aktualisierung, Tonwechsel, Fehler und erneutes Laden. Echte YouTube-Wiedergabe mit dem Produktions-Key und Abnahme auf dem Küchen-TV stehen noch aus. DNS, Plesk, TLS und Produktionszugangsdaten wurden aus dieser Entwicklungsumgebung nicht verändert.

## Benutzerrechte und Teamkonto

Die Migration `2026_10_21_110000_add_workspace_access` erhält vorhandenen Konten ihre bisherigen Administratorrechte und legt **info@hemingway-hilden.de** als Teamkonto an. Existiert diese Adresse bereits, wird das Konto eingeschränkt; sein Passwort bleibt erhalten. Neue Konten erhalten standardmäßig keine Verwaltungsrechte. Die Rollen sind `admin`, `operations` (Teamkonto) und `none` (nur eigenes Profil/Passwort).

Das Teamkonto erhält ausschließlich **Schichtübergabe, Abrechnung, Gutscheinverkauf/-einlösung und Dienstplan** einschließlich Schichten bearbeiten, kopieren, Vorlagen und PDF-Export. Zeiterfassung, Tages-/Monatsübersicht, Korrekturen, Lohnexport, Mitarbeiter-/Abteilungs-/Schichttypenverwaltung, Buchhaltung, Einstellungen und Infosystem sind gesperrt. Desktop- und Mobilnavigation verwenden die serverseitige Berechtigungsliste; direkte URLs und Verwaltungs-APIs werden ebenfalls geprüft. API-Abhängigkeiten der erlaubten Seiten bleiben nutzbar. Der Dienstplan liefert dem Teamkonto keine RFID-Tags, E-Mails, Telefonnummern oder Stundenlöhne. In der ausdrücklich erlaubten Abrechnung bleiben die dort benötigten Mitarbeiter-Stundensätze verfügbar.

Nach dem Deployment `php artisan migrate --force` ausführen. Für ein neu angelegtes Teamkonto wird kein bekanntes Standardpasswort hinterlegt. Passwort über die vorhandene Funktion **Passwort vergessen?** durch den Kontoinhaber setzen (funktionierender Mailversand vorausgesetzt), oder lokal am Server verdeckt setzen:

```sh
php artisan users:info --set-password
```

Mit `php artisan users:info` lässt sich das Konto erneut auf seine Teamrechte setzen, ohne ein bestehendes Passwort zu ändern. Eine abweichende Adresse kann ausdrücklich per `--email=adresse@example.com` angegeben werden. Anmeldungen des Teamkontos führen direkt zur Abrechnung, unberechtigte neue Registrierungen nur zum eigenen Profil. Öffentliche Selbstregistrierung verleiht keine Verwaltungsrechte; Profiländerungen können keine Rolle setzen.

Diese Änderung erstellt das Produktionskonto erst beim Ausführen der Migration auf dem Server. Es wurden keine Konten oder Passwörter in der laufenden Produktionsdatenbank geändert und keine E-Mails versendet.
