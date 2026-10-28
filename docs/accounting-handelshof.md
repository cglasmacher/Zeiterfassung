# Handelshof: strukturierte Rechnungsdaten aus dem Original-PDF

## Profil und Ablauf

`handelshof_facturx_v1` verarbeitet die eingebettete Datei `factur-x.xml` des bereitgestellten Handelshof-Formats. Das ist ein gezielter Lieferantenparser für diese Rechnungsvorlage, kein allgemeiner EN16931-/ZUGFeRD-Validator. Die XML-Daten enthalten Artikelnummern, Rechnungsgruppen, Packungsinhalt, Menge, Preis, Nettobeträge und explizite Umsatzsteuersätze. KI und Weiterleitungsabsender bestimmen diese Werte nicht.

Die Erkennung verlangt im PDF „Handelshof Köln Stiftung & Co. KG“ und `DE123046183`. Der Import prüft den XML-Verkäufer erneut und ordnet den aktiven Kreditor **70012** zu. Ein aus n8n mitgeschickter Lieferantenname ist dafür unerheblich. Andere Handelshof-Gesellschaften werden nicht still auf diesen Kreditor gebucht.

Laravel liest das hochgeladene Original mit `pdfdetach` und `pdftotext -layout`. XML-Inhalt aus dem Request wird nicht akzeptiert. Extraktion läuft in einem privaten temporären Verzeichnis, das auch bei Fehlern entfernt wird. XML darf höchstens 2 MB umfassen; DTD und Entity-Deklarationen werden abgewiesen, externe Ressourcen werden nicht geladen.

## Verbindlicher Abgleich

- Rechnungsnummer, Datum und fortlaufende PDF-Seiten.
- Jede XML-Position gegen die gedruckte Position: Nummer, Artikel, Bezeichnung, Rechnungsgruppe, Verpackungseinheit, Inhalt, Menge, Preis, Netto und Steuerkennzeichen.
- Menge × Packungsinhalt × Einzelpreis gegen Zeilennetto. In diesem Handelshof-XML bezeichnet `BasisQuantity` den gedruckten Packungsinhalt; es wird ausdrücklich keine allgemeine Factur-X-Preisformel unterstellt.
- Steuerkennzeichen 1 = 19 %, 2 = 7 % müssen zu den expliziten XML-Steuersätzen passen.
- Summen aller Positionen gegen die XML-Steuergruppen sowie gedrucktes Warenwert-/Leergutnetto, Steuer und Rechnungsbrutto.
- Steuergruppen werden bei jeder Freigabe und beim Export erneut gegen das gespeicherte Originalergebnis geprüft.

Nicht gelieferte Artikel mit Menge und Betrag 0 bleiben in den Parsernachweisen und erzeugen keine Buchungszeile. Negative Rückgaben behalten ihre Vorzeichen. Positionssteuer wird durch größte Reste auf die **gedruckte Gruppensteuer** verteilt, damit die Summe exakt bleibt. Kein Aufsummieren unabhängig gerundeter Einzelartikelsteuer.

Fehlendes oder widersprüchliches XML, fehlender PDF-Text, unbekannte Steuerkategorien, andere Währungen, Gutschrift-Rechnungsart 381, zusätzliche Zu-/Abschläge und Vorauszahlungen führen zu `analysis_failed`. Das Original und der konkrete Prüfgrund bleiben im Portal; keine Rückfallberechnung mit KI. Normale negative Pfand-/Retourenzeilen in einer Rechnung vom Typ 380 werden unterstützt. Das Profil setzt ein PDF mit Text voraus; Scans benötigen ein eigenes Verfahren.

## Kontierung

Manuell gelernte Lieferanten-/Artikelregeln haben Vorrang. Die Steuer stammt weiterhin aus dem aktuellen Beleg; ein früher gelernter Steuersatz ersetzt sie nicht.

| Gruppe / Artikel | Konto |
| --- | --- |
| Obst/Gemüse | 5113 |
| Fleisch/Wurst/Geflügel | 5111 |
| Fisch | 5112 |
| Feinkost/Mopro, Lebensmittel, Konserven, Tiefkühlkost mit 7 % | 5110 |
| Dieselben Lebensmittelgruppen mit 19 %, ohne speziellere Regel | 5130 |
| **Eiswürfel für die Bar**, Artikel 127064 oder Eiswürfel-Bezeichnung in Tiefkühlkost | **5110, mit tatsächlichem Belegsteuersatz (hier 19 %)** |
| Haferdrink 137654, Orangensaft 167506 | 5131 |
| Transportkosten 336937 / 338564 | 5131, getrennt nach 19 % / 7 % |
| Chantre 315310 | 5134 |
| Non-Food | 6301 |
| Pfand/Leergut | 1141 netto, zugehörige Vorsteuer gesondert auf 1461 |

Die Zuordnung verwendet die vorhandenen Sachkonten. Kontonamen (etwa ein vorhandenes „7 %“ im Namen von 5110) bestimmen keinen Steuersatz. Unbekannte Gruppen/Getränke/Serviceartikel ohne passende Regel bleiben zur manuellen Kontierung offen; fehlende oder inaktive Konten werden nicht angelegt/ersetzt. Korrekturen lassen sich im Portal als Artikelregel lernen.

Artikel bleiben als Prüfdetails erhalten. Vorschau und Lexware-CSV summieren **pro Beleg, Sachkonto und Steuersatz**, nicht pro Artikel. 5110 mit 7 % und 5110 mit 19 % sind zwei Gruppen. Für Pfand bleibt die bestehende explizite Aufteilung 1141 netto / 1461 Vorsteuer erhalten. Nur freigegebene Belege können exportiert werden.

## Referenzrechnung und Tests

Der bereitgestellte vierseitige Beleg vom 01.07.2026 enthält 58 XML-Positionen, davon drei nicht gelieferte Nullpositionen. Ergebnis: 55 Prüfzeilen, 1.366,33 EUR netto, 114,86 EUR Vorsteuer, **1.481,19 EUR brutto**.

- 7 %: 1.206,15 EUR netto / 84,43 EUR Steuer.
- 19 %: 160,18 EUR netto / 30,43 EUR Steuer.
- Darin Pfand: 79,92 EUR netto / 15,18 EUR Vorsteuer; Kreditoranteil 95,10 EUR.
- Eiswürfel: 36,38 EUR netto / 6,91 EUR Vorsteuer auf der 19-%-Gruppe von 5110.
- Mit den Standardzuordnungen: neun Konten-/Steuergruppen, elf CSV-Buchungssätze: eine Kreditorenbuchung über die volle Rechnungssumme und zehn Aufteilungen inklusive gesonderter Pfandvorsteuer. Die Aufteilungen laufen über das konfigurierte steuerfreie Verrechnungskonto; siehe [Lexware-Export](accounting-lexware-export.md).

Fixtures enthalten anonymisierte XML-/Layoutdaten, nicht die Original-PDF. Tests decken Import, Freigabe, Exportvorschau/-erstellung/-download, gelernte Regeln, Steuergruppenänderungen, inaktive Stammdaten und ungültige/abweichende XML-/PDF-Daten ab. Zusätzlich wird der n8n-Code mit Testdoubles geprüft. Der Parser wird lokal auch gegen das bereitgestellte Original ausgeführt. Ein produktiver n8n-Lauf und ein echter Lexware-Import sind nicht Teil dieser lokalen Tests.

## Ausrollen

1. Änderungen zusammenführen und auf dem Portalserver `git pull` ausführen. Keine neue Migration oder Frontendänderung.
2. `command -v pdftotext pdfimages pdfdetach` prüfen; alle drei kommen unter Ubuntu/Debian aus `poppler-utils`. Optional `ACCOUNTING_PDFDETACH_BINARY` setzen, falls der Pfad abweicht. Danach `php artisan config:clear`.
3. Aktiven Lieferanten 70012 und die oben verwendeten Konten inklusive 1461 prüfen. Vorhandene Stammdaten werden durch diese Änderung nicht verändert. Wenn noch nicht eingerichtet, die bestehenden Supplier-/Account-Seeder bewusst ausführen; sie aktualisieren auch vorhandene Namen/Aktivierungen.
4. Aktuellen Rechnungsworkflow sichern/deaktivieren und `workflows/n8n/Hemingway-Eingangsrechnungen.json` importieren. Drei IMAP-Credentials, OpenAI für andere Lieferanten und die beiden Portal-Bearer-Header wie bisher setzen. Es bleibt **ein** Rechnungs-Poller.
5. Mit einer neuen weitergeleiteten Handelshof-Rechnung testen: Inhaltserkennung → „Handelshof ohne KI?“ → „Handelshof Original ans Portal“ → Portal. Belegnummer, Gesamtsumme, 5110 mit beiden Steuersätzen und Pfandaufteilung in der Vorschau prüfen, anschließend freigeben.

Ein schon vorhandener Import mit derselben Mail-/Anhangsreferenz wird nicht überschrieben. Für einen erneuten Test einen noch nicht freigegebenen falschen Beleg im Portal löschen und die betreffende Mail gezielt erneut bereitstellen. Freigegebene/exportierte Belege bleiben gesperrt.
