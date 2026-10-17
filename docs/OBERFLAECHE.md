# Übersicht, Navigation und Darstellung

Die Anwendung öffnet nach Anmeldung direkt die Betriebsübersicht. Die Laravel-Startseite und das zweite Starter-Dashboard sind entfernt. Es gibt eine zentrale Navigation für Betrieb, Team & Planung, Kasse sowie Verwaltung. Das Kontomenü zeigt den angemeldeten Benutzer und dessen Profil; fiktive Admin-Rolle und Benachrichtigungspunkt sind entfernt.

## Seiten

| Aufgabe | Kanonische URL |
| --- | --- |
| Betriebsübersicht | `/dashboard` |
| Dienstplan | `/dienstplan` |
| Zeitstempel des Tages | `/team/zeiten` |
| Monatsübersicht | `/team/monat` |
| Zeiten erfassen | `/team/erfassung` |
| Zeitkorrekturen | `/team/korrekturen` |
| Schichtübergabe | `/kasse/uebergaben` |
| Tagesabschluss | `/kasse/tagesabschluss` |
| Lohnexport | `/exporte/lohn` |
| Einstellungen | `/einstellungen` |

Bestehende `/timetracking/...`-Links und frühere Dashboard-Schnellaktionsadressen unter `/time-tracking/...` leiten auf die jeweilige Seite weiter. Einstellungen können mit `?tab=employees`, `?tab=departments`, `?tab=shift-types` bzw. `?tab=general` direkt geöffnet werden. Die URL bleibt beim Tabwechsel konsistent.

Browser-APIs sind in `routes/admin-api.php` zusammengefasst und werden unter `/api` mit Anmeldung und CSRF-Schutz eingebunden. Bestehende URLs für Zeiteingaben und Exporte bleiben gleich. Nur Geräte-Endpunkte `/api/clock-in`, `/api/clock-out` und `/api/summary/current` bleiben in der bisherigen RFID-basierten API-Gruppe. Rein technische Debug- und reparierende GET-Routen sind entfernt. Integrationen, die bisher ohne Benutzeranmeldung auf Verwaltungs-APIs zugriffen, benötigen jetzt eine Sitzung; die RFID-Schnittstelle bleibt unverändert.

## Echte Betriebsdaten

Das Dashboard erhält die erste Momentaufnahme direkt als Inertia-Seitendaten. `GET /api/dashboard` aktualisiert sie einmal pro Minute; ein gescheiterter Abruf lässt die bisherigen Daten sichtbar. Es zeigt:

- tatsächlich offene Zeiteinträge mit Beginn in der Vergangenheit, einschließlich laufender Dienste vom Vortag;
- gespeicherte Stunden abgeschlossener Zeiteinträge mit Beginn am heutigen Tag, ohne geschätzte Stunden oder fiktive Trends;
- heutige geplante Schichten ohne pauschale Behauptung, ein noch nicht erschienener Mitarbeiter sei abwesend;
- offene Kassenabschlüsse, zuletzt bestätigten Bargeldbestand und reale Aufgabenhinweise;
- tatsächlich zuletzt bearbeitete Zeiteinträge und bestätigte Übergaben, chronologisch nach Zeitstempel sortiert.

Fehlende Daten führen zu Leerzuständen. Bei Ladefehlern erscheint eine Fehlermeldung; ein Fehler wird nicht als Nullbestand ausgegeben. Der angezeigte Kassenstand ist ausdrücklich der zuletzt bestätigte Bestand, keine Live-Zählung.

Die Monatsübersicht startet im aktuellen Monat und zeigt das gesamte Team oder einen ausgewählten Mitarbeiter. Ehemalige Mitarbeiter können für historische Einträge weiter ausgewählt werden. Daten stammen direkt aus abgeschlossenen Zeiteinträgen; der gesamte letzte Monatstag wird eingeschlossen. Fest eingetragener Mitarbeiter 1, Oktober 2025 und unbegründete Produktivitätsprozente sind entfernt.

Die Tagesübersicht zeigt ausschließlich Zeitstempel. Der zweite, bisherige Schichtende-/Auszahlungsdialog ist aus der Oberfläche entfernt; die neue Kassenabrechnung bleibt der zentrale Ort für Barauszahlungen. Die alten API-Endpunkte für frühere Berichte bleiben angemeldet erreichbar, verändern aber die neuen Kassenabschlüsse nicht.

## Demo-Bereinigung

Entfernt sind feste Prozenttrends, nicht geladene Live-Sektion, fiktiver Admin-Benutzer, Benachrichtigungspunkt, funktionsloser Monats-PDF-Button, irreführende LexOffice-Beschriftung und nicht verifizierte DATEV-Schaltfläche. Die CSV bleibt verfügbar; das konkrete Lexware lohn+gehalt-plus-Importprofil bleibt für später. Unbenutzte alternative Dienstplanseiten und deren Komponenten sind entfernt; der tatsächlich verwendete Dienstplan bleibt erhalten.

Der Standard-Seeder legt keine Beispielmitarbeiter oder Beispielschichten mehr an. Bestehende ursprüngliche Pausenregeln können bei Erstinstallation idempotent angelegt werden. **Es werden keine vorhandenen Produktivdatensätze pauschal gelöscht**: Ohne eindeutige Identifikation könnte ein vermeintlicher Testmitarbeiter ein echter Datensatz mit Lohn-/Zeithistorie sein. Bei vorhandenen Demo-Datensätzen erfolgt die gezielte Bereinigung nach Prüfung in der Administration.

## Design und Betrieb

Ruhige Oliv- und Naturtöne ersetzen die gemischte Farbpalette. Die gemeinsame Oberfläche verwendet kompakte Karten, Tabellen und Bedienelemente. Die Seitennavigation ist am Desktop verkleinerbar und merkt sich die Auswahl lokal. Auf kleinen Bildschirmen ist sie ein ausklappbares Menü mit Schließen-Schaltfläche und Escape-Unterstützung. Konto, Login und Profil verwenden dieselbe Gestaltung. Extern geladene Schriften sind nicht erforderlich.

Nach Übernahme wie gewohnt Abhängigkeiten und Frontend bauen sowie Route-/Konfigurationscache erneuern. Diese Überarbeitung benötigt keine neue Datenbankmigration zusätzlich zur Kassenmigration des vorherigen PRs.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
```

Backend-Prüfung mit `php vendor/bin/phpunit`. Die zusätzlichen Tests prüfen leere echte Daten, laufende Nachtschichten, zukünftige Dienste, Mitarbeiter-/Monatsfilter, historischen Monatsletzten, geschützte Verwaltungsrouten und Weiterleitungen.


## Wenn nach dem Pull noch die Starteransicht erscheint

Ein `npm run build` ändert weder PHP-Routen noch einen bestehenden Routen-Cache.
Nach Abruf und **Bereitstellung** von `main` in Plesk im tatsächlich ausgelieferten
Projektordner ausführen:

```sh
php artisan optimize:clear
bash scripts/deploy.sh
```

Die Routine baut das Frontend, führt ausstehende Migrationen aus, erneuert Routen-
und Ansichten-Cache und prüft das Ergebnis. Vor dem Deployment Datenbank sichern
und PHP-Abhängigkeiten wie gewohnt mit `composer install --no-dev --prefer-dist
--optimize-autoloader` installieren. Für eine bestimmte Plesk-PHP-Version z. B.
`PHP_BIN=/opt/plesk/php/8.3/bin/php bash scripts/deploy.sh` verwenden.
Die Routine lädt keinen Git-Stand herunter; Plesk muss ihn vorher bereitstellen.

Separat prüfen: `php artisan app:check-deployment`. Die Ausgabe nennt Projektpfad,
Dokumentenstamm und den geladenen Dashboard-Controller. Dokumentenstamm in Plesk
muss auf **dieses** `public` zeigen. Bei korrekter lokaler Prüfung und weiterhin alter
Website PHP-FPM in Plesk neu starten (OPcache) und die Seite neu laden. Eine
vorhandene `public/hot` auf dem Produktionsserver entfernen, sofern dort kein
Vite-Entwicklungsserver gewollt ist. `/login` leitet angemeldete Benutzer gewollt
auf `/dashboard`; abgemeldet zeigt es die Anmeldeseite.


## Mobile Navigation

Auf Smartphone und Tablet (bis 1023 px) bleibt eine Leiste mit **Übersicht**,
**Dienstplan**, **Schichtübergabe** und **Abrechnung** am unteren Bildschirmrand
sichtbar. Der aktive Bereich ist hervorgehoben. Weitere Seiten stehen über die
beschriftete **Menü**-Schaltfläche oben links bereit. Die Inhalte haben Abstand
zur unteren Leiste einschließlich des Geräte-Sicherheitsbereichs.

Die Gruppe **Kasse & Abrechnung** steht im Hauptmenü vor Team & Planung. Das
Dashboard bietet zwei eigene Einstiegskarten. In der Kasse sind Schichtübergabe
und Abrechnung zusätzlich direkt als Reiter erreichbar. **Abrechnung** bezeichnet
hier den Tagesabschluss; der Lohnexport bleibt ein eigener Bereich.
Ungespeicherte Kasseneingaben lösen beim Seitenwechsel eine Rückfrage aus.

Mobile Kassenformulare verwenden einspaltige Felder, größere Eingaben (16 px,
damit iOS beim Fokussieren nicht automatisch vergrößert), mindestens 44 px hohe
Bedienelemente und volle Schaltflächenbreite. Die fachliche Berechnung bleibt
unverändert.
