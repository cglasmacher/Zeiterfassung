# 🍽️ Hemingway Gastro - Upgrade Guide

## 🎨 Design & Funktions-Upgrade

Dieses Upgrade modernisiert Ihr Arbeitserfassungssystem mit einem komplett neuen Design und erweiterten Funktionen.

## 📦 Installation

### 1. Abhängigkeiten installieren

```bash
npm install lucide-react
```

### 2. Entwicklungsserver starten

```bash
composer run dev
```

Dies startet automatisch:
- PHP Artisan Server
- Queue Worker
- Logs (Pail)
- Vite Dev Server

## ✨ Neue Features

### Design-System
- ✅ Modernes Gastro-Design mit warmen Farben
- ✅ Glassmorphism-Effekte
- ✅ Konsistente Farbpalette (Orange, Blau, Gold)
- ✅ Responsive Layout für alle Geräte
- ✅ Tailwind CSS v4 Migration (MUI entfernt)

### Dashboard
- ✅ Live-Statistiken (Anwesenheit, Arbeitsstunden, etc.)
- ✅ Echtzeit-Aktivitätsanzeige
- ✅ Schnellaktionen für häufige Aufgaben
- ✅ Abwesenheits-Widget
- ✅ Hinweise und Warnungen
- ✅ Aktivitäts-Feed

### Dienstplanung
- ✅ Verbessertes Drag & Drop
- ✅ Farbcodierung nach Schichttyp
- ✅ Wochenstatistiken
- ✅ Konflikterkennung
- ✅ Woche kopieren/einfügen
- ✅ PDF-Export
- ✅ Vorlagen speichern

### Mitarbeiterverwaltung
- ✅ Moderne Karten-Ansicht
- ✅ Erweiterte Suchfunktion
- ✅ Statusfilter
- ✅ Schnellstatistiken
- ✅ Detaillierte Mitarbeiterprofile

### Übersichten
- ✅ Modernisierte Tages- und Monatsübersichten
- ✅ Visuelle Statistiken
- ✅ Produktivitätsmetriken
- ✅ Verbesserte Navigation

### Export
- ✅ Neues Export-Interface
- ✅ Export-Verlauf
- ✅ Schnellzugriff-Optionen
- ✅ Vorschau-Funktion

## 🎨 Farbschema

```css
Primary (Orange):   #ff6b35 - Energie & Gastfreundschaft
Secondary (Blau):   #004e89 - Professionalität
Accent (Gold):      #f7b801 - Premium-Gefühl
Success (Grün):     #2ecc71 - Aktiv/Eingecheckt
Warning (Bernstein): #f39c12 - Warnung
Error (Rot):        #e74c3c - Fehler
```

## 🚀 Nächste Schritte

### Empfohlene Erweiterungen:

1. **Mitarbeiter-Portal**
   - Self-Service für Mitarbeiter
   - Schichttausch-Funktion
   - Urlaubsanträge

2. **Analytics & Reporting**
   - Erweiterte Berichte
   - Kosten-Analyse
   - Trend-Analysen

3. **Kommunikation**
   - Team-Chat
   - Ankündigungen
   - Benachrichtigungen

4. **Mobile App**
   - Progressive Web App (PWA)
   - Offline-Modus
   - Push-Benachrichtigungen

## 📝 Komponenten-Bibliothek

Neue wiederverwendbare Komponenten:

- `Card` - Moderne Karten mit Glassmorphism
- `Button` - Verschiedene Varianten (primary, secondary, outline, ghost)
- `Badge` - Status-Badges
- `Input` - Formular-Eingaben mit Icons
- `Select` - Dropdown-Menüs
- `Toast` - Benachrichtigungen

## 🎯 Best Practices

1. **Konsistenz**: Verwenden Sie die vordefinierten Komponenten
2. **Farben**: Nutzen Sie das Farbschema aus tailwind.config.js
3. **Icons**: Verwenden Sie Lucide React Icons
4. **Spacing**: Folgen Sie dem 4px-Grid-System
5. **Animationen**: Nutzen Sie die vordefinierten Animationen

## 🐛 Troubleshooting

### Icons werden nicht angezeigt
```bash
npm install lucide-react
```

### Styles werden nicht geladen
```bash
npm run build
```

### Tailwind funktioniert nicht
Stellen Sie sicher, dass `@import "tailwindcss";` in `resources/css/app.css` vorhanden ist.

## 📚 Dokumentation

- [Tailwind CSS](https://tailwindcss.com/)
- [Lucide Icons](https://lucide.dev/)
- [React](https://react.dev/)
- [Inertia.js](https://inertiajs.com/)
- [Laravel](https://laravel.com/)

## 🎉 Fertig!

Ihr System ist jetzt modernisiert und bereit für den Einsatz!