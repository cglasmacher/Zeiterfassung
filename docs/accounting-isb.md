# ISB Seafood: Fisch und Fleisch

`isb_layout_v1` liest die native einseitige PDF-Vorlage mit `pdftotext -layout`. Es gibt kein eingebettetes XML im bereitgestellten Muster. PDF-Name und USt-ID (ISB Seafood GmbH / DE236175659) werden sowohl in der Erkennung als auch im Parser geprüft. Der aktive Kreditor ist 70018. Der Weiterleitungsabsender entscheidet nicht über den Lieferanten.

## Kontierung und Beträge

- Fisch und Meeresfrüchte: 5112. Bestätigte Artikel 004179 Garnelen, 004243 Lachsfilet.
- Fleisch und Geflügel: 5111. Bestätigte Artikel 004430 Kalbsrücken, 004501 Hähnchenbrust.
- Gelernte Lieferanten-/Artikelregeln behalten Vorrang. Sie ersetzen nicht die aktuelle Belegsteuer.
- Weitere eindeutige Fisch-/Fleischbezeichnungen werden konservativ erkannt. Unbekannte oder gemischte Beschreibungen bleiben zur Prüfung offen; fehlende/inaktive Konten werden nicht ersetzt.

Rechnungsnummer (einschließlich führender Nullen), Datum, Positionsfolge, Artikel, Mengen, Preise, Nettobeträge und explizite 7-/19-%-Angaben stammen aus dem Original. Zeilenumbrüche in Beschreibungen werden zusammengeführt. Menge × Preis, Nettosumme, gedruckte Steuergruppen und Brutto werden abgeglichen. Die Positionssteuer wird auf die gedruckte Gruppensteuer abgestimmt; Steuergruppen werden bei Freigabe und Export erneut geprüft.

Das Muster ergibt vier Prüfpositionen und zwei Konten-/Steuergruppen: Fisch netto 248,89 EUR / Steuer 17,42 EUR, Fleisch netto 249,29 EUR / Steuer 17,45 EUR. Gesamt 498,18 EUR netto, 34,87 EUR Vorsteuer, 533,05 EUR brutto.

Der bestehende Export erzeugt drei CSV-Sätze: eine Kreditorenbuchung über die volle Rechnungssumme mit ausschließlich der Rechnungsnummer als Text sowie die beiden Gruppen gegen das konfigurierte Verrechnungskonto (1371 „Import Durchlauf“). Die Details und Splittexte bleiben erhalten. [Lexware-Export](accounting-lexware-export.md).

## Grenzen und Fehler

Das Profil ist anhand einer nativen einseitigen Rechnung geprüft. Mehrseitige Belege, Scans, andere Vorlagen, unvollständige Positionen, fehlende Steuerangaben und abweichende Summen führen zu einem gespeicherten Original mit `analysis_failed`; keine KI-Ersatzbeträge. Das Format benötigt beide gedruckten Steuerzeilen für 7 % und 19 %, auch wenn eine davon null ist. Weitere Muster können gezielt ergänzt werden.

## Einrichtung

1. Portaländerung ausrollen (`git pull`); keine neue Migration und keine Frontendänderung. Aktiver Lieferant 70018 und Konten 5111/5112 müssen vorhanden sein. Der bestehende Export benötigt zusätzlich das konfigurierte Konto 1371 wie in der Exportanleitung beschrieben.
2. Aktualisierte `workflows/n8n/Hemingway-Eingangsrechnungen.json` importieren. Bestehenden Rechnungs-Poller vorher sichern und deaktivieren. IMAP-Credentials und beide Portal-Bearer-Header übernehmen; OpenAI bleibt für sonstige Lieferanten notwendig.
3. Am Nein-Ausgang von „Handelshof ohne KI?“ folgt jetzt „ISB ohne KI?“. Der Ja-Zweig „ISB Original ans Portal“ übergibt das verknüpfte Original ohne KI-Positionen. Der Nein-Zweig führt weiter zu GPT. Fehler gelangen in die bestehende PDF-Fehlerbehandlung.
4. Neue ISB-Mail zum Live-Test weiterleiten; im Portal Original, Rechnungsnummer, beide Kontensummen, Steuer und Gesamtbetrag prüfen. Anschließend freigeben und Exportvorschau prüfen.

Es bleibt ein Rechnungs-Poller. Wiederholungen überschreiben keine Originale, manuellen Korrekturen oder Freigaben. Ein Fehlerbeleg wird im Portal geprüft, auch wenn die Mail nach bestätigter Originalspeicherung archiviert wurde.

Die Tests verwenden anonymisierten Layouttext und Process-/n8n-Testdoubles. Die Original-PDF wird nicht eingecheckt. Zusätzlich wird die lokale Extraktion am Original geprüft. Ein produktiver n8n-Lauf und ein echter Lexware-Import sind hier nicht verfügbar.
