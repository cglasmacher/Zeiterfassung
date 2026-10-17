# Hemingway Design

Die Markenassets sind direkt aus der bereitgestellten PDF „Grüner Kreis: Bild überlappen verhindern (500 x 500 mm)“ abgeleitet. Das vollständige Originalmotiv bleibt erhalten: grüner Kreis, cremefarbener Papierstreifen, schwarzer Hemingway-Schriftzug und „Café · Bar · Restaurant“. Kein neu gezeichnetes Ersatzlogo.

- Originales Logo: `public/brand/hemingway-logo.png`, 512 × 512 Pixel mit transparenten Ecken.
- Favicons: SVG, ICO mit mehreren Größen und PNG mit 32 Pixeln. Apple-Touch-Icon mit 180 Pixeln.
- Grün aus der PDF-Rasterung: `#8cae76`.
- Creme/Papier: `#e7e6db`, helle Arbeitsflächen `#faf9f4`.
- Dunkles Aktionsgrün: `#486338` für weiße Buttonbeschriftungen und Links. Das helle Originalgrün ist als Fläche mit dunkler Schrift gedacht.

Das Original erscheint auf der Anmeldung und in der Navigation. Die kompakte Seitennavigation verwendet dasselbe Motiv verkleinert. Navigation, aktive Menüpunkte, Kassenkarten, Formulare, mobile Navigation und Ladebalken verwenden die angepasste Palette. Tabellen und Arbeitsflächen bleiben ruhig und gut lesbar. Funktionsfarben für Fehler/Warnungen sind weiterhin unterscheidbar.

Der Tabtitel verwendet Hemingway. Favicon-URLs haben einen neuen Versionsparameter, damit Browser das bisherige Monogramm ersetzen. Nach Merge und Plesk-Bereitstellung Frontend neu bauen und Ansichten-Cache leeren (`bash scripts/deploy.sh`). Keine zusätzliche Datenbankmigration für diese Designänderung.
