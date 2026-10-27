# Phase 1: Import und Freigabe absichern

## Verhalten

- `source_reference` ist beim Import erforderlich. Zusammen mit `source` ist sie eindeutig.
- Der erste Import liefert HTTP 201 und `duplicate: false`. Jeder weitere gültige Import derselben Referenz liefert HTTP 200 und `duplicate: true`, ohne den bestehenden Beleg, seine Positionen oder sein PDF zu verändern. Auch unfertige Belege werden bei Wiederholung nicht neu analysiert.
- Die bestehende n8n-Datei liefert bereits eine Quellreferenz und bleibt mit diesem Schritt kompatibel.
- Gelernte Artikelregeln gewinnen vor gelernten exakten Beschreibungen, weiteren gelernten Lieferantenregeln, festen Lieferantenregeln und dem externen Kontovorschlag. Innerhalb einer Gruppe gelten Priorität und ID. Regeln bleiben auf ihren Lieferanten begrenzt.
- Herkunft, Konfidenz und Regelschlüssel stammen aus der tatsächlich angewendeten Portalauflösung. Externe Herkunftsangaben dürfen eine KI-Kontierung nicht als Lernregel ausgeben.
- Lernregeln bestimmen nur Konten. Fehlende Steuerdaten werden niemals aus historischen Artikelregeln ergänzt. Bestehende Steuerwerte in Regeln bleiben gespeichert, werden aber nicht mehr angewendet.
- Dieselbe fachliche Prüfung steuert Bereitschaft und Freigabe: Lieferant/Kreditor, Rechnungsnummer/-datum, gespeichertes PDF, Konten, Steuerdaten, offene Positionsprüfung und Netto-/Steuer-/Bruttosummen. Übermittelte `validation_errors` bleiben blockierend.
- Freigegebene/exportierte Belege und Belege mit Freigabe-/Exportmarkierungen sind gegen Kontierungsänderung und erneute Freigabe gesperrt (HTTP 409).
- Im Review erscheinen aktuelle Sperrgründe. Eine Position kann mit unverändertem Konto bestätigt und als Artikelregel gespeichert werden.

## Deployment

1. Datenbanksicherung erstellen und Importe während der Migration pausieren.
2. `php artisan migrate --force` ausführen. Die neue Migration prüft vorhandene doppelte Quellreferenzen und bricht bei einem Fund vor dem Anlegen des Index ab. Sie löscht oder vereinigt keine Belege.
3. `npm install` und `npm run build` ausführen. Bereits auf dem Ausgangsstand fehlt `qrcode` im Lockfile; deshalb kann dort `npm ci` fehlschlagen.
4. Mit einer Testrechnung importieren, kontieren, wiederholen und freigeben. Der zweite Import muss dieselbe Beleg-/Positions-ID und denselben PDF-Pfad liefern.

## Prüfung

`php artisan test --filter=AccountingImportTest`

Die Featuretests prüfen Regelvorrang und Lieferantengrenzen, fehlende Steuer/PDF/Stammdaten, Summenabweichungen, importierte Prüfprobleme, manuelle Korrekturen bei Wiederholung, Freigabe-/Exportsperren, fehlende Quellreferenzen, Authentifizierung und den eindeutigen Index.

## Folgeschritte

Dieser Schritt ändert den produktiven n8n-Workflow nicht. Vectron-Ausschluss, separate Verarbeitung jedes PDF-Anhangs, anhangsspezifische Referenz, Gato-Sternchenbelege und Steuergruppenabgleich folgen anschließend.

Unterschiedliche Quellreferenzen derselben Rechnung werden hier noch nicht über Rechnungsnummer oder Dateihash zusammengeführt. Ein bewusster neuer Analyselauf und das dokumentierte Korrigieren/Quittieren importierter Prüfprobleme benötigen einen eigenen Review-Prozess. Ein Wiederholungsimport ist dafür ausdrücklich kein Ersatz. Bereits gespeicherte Status werden nicht rückwirkend neu berechnet; die Freigabe prüft stets den aktuellen Beleg erneut.
