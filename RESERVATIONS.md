# Hemingway Reservierungen

## Tischplan-Builder – erster Baustein

Aufruf als Administrator: **Betrieb → Tischplan-Builder** (`/reservierungen/tischplan`). Der Builder ist in die bestehende Weboberfläche und damit auch in die App eingebunden. Eine separate iPad-App ist nicht vorgesehen.

Nach dem Deployment:

```bash
php artisan migrate --force
npm install
npm run build
php artisan optimize:clear
```

### Bedienung

1. „Raum anlegen“ wählen. Ein leerer Raum oder die Startvorlagen „Restaurant“ und „Theke“ stehen bereit.
2. Die Restaurantvorlage enthält Tische 1–8 und 11–17 mit jeweils zwei Plätzen. Die Thekenvorlage enthält Tisch 21 mit vier und Tisch 20 mit acht Plätzen. Ihre Anordnung ist vorläufig, kein rekonstruierter Grundriss.
3. Tische und Wände, Türen, Theke, Pflanzen oder Texte hinzufügen. Objekte im Plan oder Tische in der Liste auswählen.
4. Objekte per Maus oder Touch verschieben. Name, Plätze, Form, Größe, Position und Drehung rechts bearbeiten. Die Position bezeichnet den Objektmittelpunkt. Maße sind Planeinheiten, keine verbindlichen Zentimeterangaben.
5. Optional am Raster ausrichten; mit Zoom und Scrollen größere Pläne bearbeiten. Die Pfeiltasten bewegen ein ausgewähltes Objekt, wenn die Zeichenfläche den Fokus hat. Umschalt bewegt in Zehnerschritten.
6. Rückgängig/Wiederholen oder Strg/⌘ Z verwenden. Duplikate erhalten neue Kennungen und Tischnamen.
7. „Änderungen speichern“ speichert den gesamten ausgewählten Raum. Raumwechsel und Verlassen der Seite warnen vor ungespeicherten Änderungen. Das Anlegen eines Raums selbst wird sofort gespeichert.

### Speicherung und Schutz

- Räume und Tische besitzen dauerhafte UUIDs. Layoutänderungen ersetzen Tischkennungen nicht.
- Entfernte Tische werden weich gelöscht; erneutes Anlegen derselben Kennung ist nicht möglich. Nach dem Speichern beginnt die Rückgängig-Historie neu.
- Änderungen am Raum erfolgen in einer Datenbanktransaktion. Revision und Zeilensperre verhindern das Überschreiben durch einen veralteten Editor. Bei HTTP 409 bleibt der lokale Entwurf erhalten; ein explizites Neuladen verwirft ihn nach Bestätigung.
- Serverseitige Validierung prüft unter anderem eindeutige Tischnamen je Raum, Kapazitäten, fremde Tischkennungen, Objekttypen und Raumgrenzen einschließlich gedrehter Objekte.
- Räumliche Überschneidungen sind im Gestaltungsmodus erlaubt. Sie stellen keine tatsächlichen Reservierungen dar.
- Alle Seiten und API-Endpunkte sind nur für Administratoren zugänglich. Bestehende Teamrechte werden nicht erweitert.
- Keine automatische Veränderung von Resmio, Online-Buchungen oder Küchen-TV.

## Vereinbarte weitere Entwicklung

Zielumfang: funktionale Orientierung an Resmio Ultimate samt Add-ons; die Funktionen der iPad-App werden in die Gesamtlösung integriert. Der Builder ist vorgezogen. Reservierungsbuch und Live-Belegung sind damit noch nicht umgesetzt.

Geplante Bausteine:

1. Reservierungsregeln, Tischkombinationen mit eigener Kapazität, Sperrungen und transaktionssichere Verfügbarkeit.
2. Reservierungsbuch, Status, Tages-/Wochen-/Agenda-/Zeitstrahlansicht, Serien, Walk-ins und Warteliste.
3. Online-Widget, Gastverwaltung, Bestätigungen, Erinnerungen, Nachrichten und Stornierungen.
4. Anzahlungen, No-Show-Abwicklung, Automatisierungen, Auswertungen und bestehende Dienstplan-/TV-Anbindung.
5. Marketing, Feedback, Erweiterung bestehender Gutscheine/Tickets sowie weitere vereinbarte Module. Externe Buchungskanäle, SMS, Telefonassistent und Zahlungsdienste benötigen eigene Integrationen.

Vor späterer Buchungsintegration müssen das Entfernen von belegten Tischen und betroffene zukünftige Buchungen zusätzlich geprüft werden. Ein erfolgreicher Builder-Speichervorgang allein bestätigt keine Tischverfügbarkeit.

### Aus den Screenshots übernommene Planungswerte (noch nicht als Regeln implementiert)

- Automatische Bestätigung bis 8 Personen; online 1–8 Personen.
- Mo und Mi–So 10:00–20:00; 30-Minuten-Intervall, insgesamt 30 Minuten Vorlauf, 365 Tage Buchungshorizont.
- Aufenthaltsdauer: Standard 90, ab 2 Personen 105, ab 4 Personen 135, ab 6 Personen 150 Minuten.
- Name und Telefon bei manuellen Buchungen verpflichtend; E-Mail optional.
- Erinnerungen, Antwortfunktion, Online-Warteliste und Raumauswahl einschließlich „Beliebiger Raum“ aktiv; PIN-Prüfung aus.
- Automatische Tischzuordnung und individuelle Kombinationen aktiv; manuelle Überbuchung und gemeinsame Online-Belegung durch fremde Gruppen aus.
- Tischkombinationen/Plätze: 1+3/5, 1+3+4/7, 6+7/5, 4+6+7/7, 11+12+13/8, 17+15+16/7, 11+12/5, 17+16/5, 13+14/4, 11+12+13+14/9, 1+2+3/7.
- Interne Tagesfilter: Frühstück 10:00–11:55, Mittagstisch 12:00–16:55, Abendessen 17:00–23:55. TV-Zeitblöcke separat.
- Feiertagsausnahmen, aktivierte Reservierungs-Tags und differenzierte Mailvorlagen aus den bereitgestellten Screenshots berücksichtigen. Die Datumsbereichs-Semantik der Silvester-Ausnahme ist vor Übernahme zu klären.

### Prüfung

```bash
php artisan test --filter=FloorPlanTest
node --test tests/Workflow/floor-plan.test.mjs
npm run build
```

Die Featuretests verwenden die Benutzer-/Berechtigungs- und Builder-Migrationen samt benötigten Abhängigkeiten isoliert. Eine bestehende HACCP-Migration verwendet MySQL-spezifisches `SHOW INDEX` und blockiert ansonsten den SQLite-Testlauf unabhängig vom Builder. Die Gesamtanwendung wird damit nicht als vollständig getestet ausgewiesen.
