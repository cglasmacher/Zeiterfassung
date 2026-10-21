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

Die TV-Ansicht hat große Karten, blättert längere Listen automatisch durch und rotiert Medien. Sie startet stumm; **Ton an** und **Vollbild** benötigen eine Benutzeraktion. YouTube kann Werbung zeigen, Einbettung einzelner Videos verbieten oder auf bestimmten TV-Browsern zusätzliche Interaktion erfordern. Es wird YouTube eingebettet, keine YouTube-App ferngesteuert.

## Bereitstellung auf Plesk

1. Datenbank und privaten Storage sichern, Branch übernehmen; `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`.
2. `php artisan migrate --force`, `php artisan optimize:clear`, anschließend `php artisan config:cache` und bei Bedarf `php artisan route:cache`.
3. `stream.hemingway-hilden.de` als zusätzlichen Host derselben Anwendung mit demselben `public`-Document-Root einrichten. DNS auf den Plesk-Server und ein gültiges TLS-Zertifikat setzen. Keinen zweiten Laravel-Installationsstand anlegen. `APP_URL` bleibt `https://dienstplan.hemingway-hilden.de`; `INFO_STREAM_HOST=stream.hemingway-hilden.de`.
4. Session-Cookies auf den Verwaltungs-Host beschränken (`SESSION_DOMAIN` leer lassen), `SESSION_SECURE_COOKIE=true`. Die Display-Verbindung immer per HTTPS öffnen.
5. Scheduler einmal pro Minute: `php artisan schedule:run`. Queue-Worker betreiben, z. B. `php artisan queue:work --tries=3 --timeout=180`; Queue-`retry_after` muss über 180 Sekunden liegen. Alternativ für den Sync-Job eine entsprechend konfigurierte separate Queue verwenden. Fehlgeschlagene Jobs überwachen und nach Behebung erneut ausführen.
6. PHP `upload_max_filesize` mindestens 100M, `post_max_size` größer als 100M und gegebenenfalls nginx `client_max_body_size` anpassen. Dateien bleiben im privaten lokalen Storage, werden nur nach Display-Prüfung ausgeliefert und bei Entfernen eines Mediums gelöscht.
7. Für `/display/*/events` nginx-Buffering deaktivieren (`X-Accel-Buffering: no` wird gesetzt), Proxy-Lesezeit mindestens 30 Sekunden. Mehrere PHP-FPM-Worker bereitstellen. Ein Display hält jeweils einen SSE-Request für bis zu 20 Sekunden offen. Ohne funktionierendes SSE lädt die Ansicht alle 15 Sekunden nach. Der eingebaute PHP-Entwicklungsserver mit nur einem Worker ist deshalb kein geeigneter Lasttest.

Auf dem Stream-Host sind Verwaltungs-, Login- und Integrationspfade gesperrt. Die eigentlichen Display-Endpunkte sind auch auf dem Verwaltungs-Host tokenpflichtig nutzbar. Token-Queryparameter des einmaligen Verbindungslinks dürfen nicht in dauerhaft gespeicherten Access-Logs landen; am Proxy redigieren. Danach wird auf eine URL ohne Token weitergeleitet (`no-store`, `no-referrer`).

## resmio: Implementierungsstand und erforderlicher Live-Abgleich

Die öffentlich zugängliche resmio-Dokumentation bestätigt POST-Webhooks mit Basic Auth und den Feldern `id`, `facility_id`, `action`. Sie enthalten keine Reservierungsdetails: https://www.resmio.com/hilfe/webhook/ . Webhooks sind laut Anbieter ab PREMIUM verfügbar.

Der API-Zugang des Hemingway und dessen vollständige API-Spezifikation liegen dieser Implementierung **nicht** vor. Die folgenden Adapter-Annahmen wurden mit simulierten Antworten getestet, nicht gegen ein echtes resmio-Konto verifiziert:

- Basis `RESMIO_BASE_URL=https://api.resmio.com/v1` und Lesepfad `/facility/{facility}/booking/`.
- Vom Anbieter übergebener vollständiger `Authorization`-Header, verschlüsselt in der Datenbank gespeichert.
- Filter `date__gte`, `date__lte`, `limit`, Antwort `objects` und optionale Paginierung `meta.next`.
- Buchungsfelder `id`, `date` (ISO oder Unix-Sekunden), `num_people`, optional `first_name`, `last_name`, `tables`, `status`.

**Vor Aktivierung sind Pfad, Authentifizierung, Filter, Statuswerte und Feldnamen anhand der für das Konto freigegebenen resmio-Dokumentation zu bestätigen und gegebenenfalls im Adapter anzupassen.** Ein erfolgreicher Mock-Test ist kein Nachweis einer fertigen Live-Anbindung. Insbesondere muss der Lesepfad einen vollständigen Snapshot des abgefragten Zeitfensters liefern. Erst nach Bestätigung `RESMIO_RECONCILE_MISSING=true` setzen: nur dann werden nicht mehr gelieferte Reservierungen als entfernt markiert. Standardmäßig bleiben solche Datensätze erhalten; Lösch-Webhooks und ausdrücklich gelieferte Stornostatus werden trotzdem verarbeitet.

In der Verwaltung Facility-ID, Authorization sowie eigene Basic-Auth-Zugangsdaten für den Webhook eintragen, zunächst speichern und **Jetzt synchronisieren** testen. Webhook in resmio für `BOOKING_CREATED`, `BOOKING_UPDATED`, `BOOKING_DELETED` auf `https://dienstplan.hemingway-hilden.de/api/integrations/resmio/webhook` registrieren. Ausschließlich passende Facility und Buchungsaktionen werden akzeptiert. Löschungen werden sofort lokal als solche markiert; anschließend lädt ein Queue-Job die Details nach. Regelabgleich alle fünf Minuten, manuell auch mit `php artisan info:sync-resmio`.

Alle Seiten werden zuerst validiert und dann atomar übernommen. Ein Abgleich ist zeitlich begrenzt und per Lock gegen parallele Ausführung geschützt. Fehler bewahren den bisherigen Datenbestand und erzeugen einen sichtbaren Fehlerstatus. Nur HTTPS und Seiten innerhalb des konfigurierten API-Pfades sind zulässig; Weiterleitungen werden nicht verfolgt. Doppelte IDs erzeugen keine doppelten Reservierungen. Gelöschte Buchungen werden durch eine überholte API-Antwort nicht wieder aktiviert. Reservierungen älter als sieben Tage werden beim erfolgreichen Abgleich entfernt.

## Tests

`php artisan test` prüft bestehende Features und das Infosystem. Neue Tests decken Verwaltungsrechte, Display-Cookies/Tokenrotation, Aufgabenstatus, Zeit-/Displayfilter, Gastnamen, API-Paginierung, fehlerhafte Antworten, Schutz vor Credential-Weiterleitung, Webhook-Authentifizierung, UTC/Sommerzeitwechsel und private Uploads ab.

`node --test tests/Workflow/*.test.mjs` prüft bestehende n8n-Workflows und die YouTube-Linkverarbeitung. `npm run build` baut die komplette Anwendung mit dem bestehenden Lockfile.

Die lokale Playwright-Browserprüfung konnte in dieser Umgebung nicht ausgeführt werden, da die Browser-Downloads scheitern. Eine Abnahme auf dem echten Küchen-TV und ein resmio-Live-Test sind zusätzlich erforderlich. DNS, Plesk, TLS und Produktionszugangsdaten wurden aus dieser Entwicklungsumgebung nicht verändert.

## Benutzerrechte und Teamkonto

Die Migration `2026_10_21_110000_add_workspace_access` erhält vorhandenen Konten ihre bisherigen Administratorrechte und legt **info@hemingway-hilden.de** als Teamkonto an. Existiert diese Adresse bereits, wird das Konto eingeschränkt; sein Passwort bleibt erhalten. Neue Konten erhalten standardmäßig keine Verwaltungsrechte. Die Rollen sind `admin`, `operations` (Teamkonto) und `none` (nur eigenes Profil/Passwort).

Das Teamkonto erhält ausschließlich **Schichtübergabe, Abrechnung, Gutscheinverkauf/-einlösung und Dienstplan** einschließlich Schichten bearbeiten, kopieren, Vorlagen und PDF-Export. Zeiterfassung, Tages-/Monatsübersicht, Korrekturen, Lohnexport, Mitarbeiter-/Abteilungs-/Schichttypenverwaltung, Buchhaltung, Einstellungen und Infosystem sind gesperrt. Desktop- und Mobilnavigation verwenden die serverseitige Berechtigungsliste; direkte URLs und Verwaltungs-APIs werden ebenfalls geprüft. API-Abhängigkeiten der erlaubten Seiten bleiben nutzbar. Der Dienstplan liefert dem Teamkonto keine RFID-Tags, E-Mails, Telefonnummern oder Stundenlöhne. In der ausdrücklich erlaubten Abrechnung bleiben die dort benötigten Mitarbeiter-Stundensätze verfügbar.

Nach dem Deployment `php artisan migrate --force` ausführen. Für ein neu angelegtes Teamkonto wird kein bekanntes Standardpasswort hinterlegt. Passwort über die vorhandene Funktion **Passwort vergessen?** durch den Kontoinhaber setzen (funktionierender Mailversand vorausgesetzt), oder lokal am Server verdeckt setzen:

```sh
php artisan users:info --set-password
```

Mit `php artisan users:info` lässt sich das Konto erneut auf seine Teamrechte setzen, ohne ein bestehendes Passwort zu ändern. Eine abweichende Adresse kann ausdrücklich per `--email=adresse@example.com` angegeben werden. Anmeldungen des Teamkontos führen direkt zur Abrechnung, unberechtigte neue Registrierungen nur zum eigenen Profil. Öffentliche Selbstregistrierung verleiht keine Verwaltungsrechte; Profiländerungen können keine Rolle setzen.

Diese Änderung erstellt das Produktionskonto erst beim Ausführen der Migration auf dem Server. Es wurden keine Konten oder Passwörter in der laufenden Produktionsdatenbank geändert und keine E-Mails versendet.
