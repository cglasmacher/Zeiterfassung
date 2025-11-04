# 🧩 Komponenten-Dokumentation

## UI-Komponenten Bibliothek

Alle Komponenten befinden sich in `resources/js/Components/ui/`

---

## 📦 Card

Moderne Karten-Komponente mit verschiedenen Varianten.

### Import
```jsx
import { Card, CardHeader, CardBody, CardFooter, CardTitle, CardDescription } from '@/Components/ui/Card';
```

### Verwendung
```jsx
<Card glass hover>
  <CardHeader>
    <CardTitle>Titel</CardTitle>
    <CardDescription>Beschreibung</CardDescription>
  </CardHeader>
  <CardBody>
    Inhalt
  </CardBody>
  <CardFooter>
    Footer
  </CardFooter>
</Card>
```

### Props
- `glass` (boolean) - Glassmorphism-Effekt
- `hover` (boolean) - Hover-Effekt (default: true)
- `className` (string) - Zusätzliche CSS-Klassen

---

## 🔘 Button

Vielseitige Button-Komponente mit verschiedenen Varianten.

### Import
```jsx
import Button from '@/Components/ui/Button';
```

### Verwendung
```jsx
<Button 
  variant="primary" 
  size="md"
  loading={false}
  icon={<Icon />}
  onClick={handleClick}
>
  Button Text
</Button>
```

### Props
- `variant` - 'primary' | 'secondary' | 'outline' | 'ghost' | 'success' | 'warning' | 'error'
- `size` - 'sm' | 'md' | 'lg' | 'xl'
- `loading` (boolean) - Zeigt Spinner
- `disabled` (boolean) - Deaktiviert Button
- `icon` (ReactNode) - Icon-Element

### Varianten
```jsx
<Button variant="primary">Primary</Button>
<Button variant="secondary">Secondary</Button>
<Button variant="outline">Outline</Button>
<Button variant="ghost">Ghost</Button>
<Button variant="success">Success</Button>
<Button variant="warning">Warning</Button>
<Button variant="error">Error</Button>
```

---

## 🏷️ Badge

Status-Badges für verschiedene Zustände.

### Import
```jsx
import Badge from '@/Components/ui/Badge';
```

### Verwendung
```jsx
<Badge variant="success" icon={<CheckIcon />}>
  Aktiv
</Badge>
```

### Props
- `variant` - 'success' | 'warning' | 'error' | 'primary' | 'secondary' | 'neutral'
- `icon` (ReactNode) - Icon-Element
- `className` (string) - Zusätzliche CSS-Klassen

---

## 📝 Input

Formular-Eingabefeld mit Label und Fehlerbehandlung.

### Import
```jsx
import Input from '@/Components/ui/Input';
```

### Verwendung
```jsx
<Input
  label="E-Mail"
  type="email"
  value={email}
  onChange={(e) => setEmail(e.target.value)}
  error={errors.email}
  icon={<MailIcon />}
  placeholder="ihre@email.de"
/>
```

### Props
- `label` (string) - Label-Text
- `error` (string) - Fehlermeldung
- `icon` (ReactNode) - Icon-Element
- `className` (string) - Zusätzliche CSS-Klassen
- Alle Standard-Input-Props

---

## 📋 Select

Dropdown-Menü für Auswahlen.

### Import
```jsx
import Select from '@/Components/ui/Select';
```

### Verwendung
```jsx
<Select
  label="Monat"
  value={month}
  onChange={(e) => setMonth(e.target.value)}
  options={[
    { value: 1, label: 'Januar' },
    { value: 2, label: 'Februar' },
  ]}
  error={errors.month}
/>
```

### Props
- `label` (string) - Label-Text
- `error` (string) - Fehlermeldung
- `options` (array) - Array von {value, label} Objekten
- `className` (string) - Zusätzliche CSS-Klassen

---

## 🔔 Toast

Benachrichtigungssystem mit verschiedenen Typen.

### Import
```jsx
import { ToastProvider, useToast } from '@/Components/ui/Toast';
```

### Setup (in app.jsx)
```jsx
<ToastProvider>
  <App {...props} />
</ToastProvider>
```

### Verwendung
```jsx
function MyComponent() {
  const toast = useToast();
  
  const handleClick = () => {
    toast.success('Erfolgreich gespeichert!');
    toast.error('Ein Fehler ist aufgetreten');
    toast.warning('Warnung!');
    toast.info('Information');
  };
  
  return <button onClick={handleClick}>Zeige Toast</button>;
}
```

### Methoden
- `toast.success(message, duration)` - Erfolgs-Toast
- `toast.error(message, duration)` - Fehler-Toast
- `toast.warning(message, duration)` - Warn-Toast
- `toast.info(message, duration)` - Info-Toast

---

## 🪟 Modal

Modale Dialoge mit verschiedenen Größen.

### Import
```jsx
import Modal from '@/Components/ui/Modal';
```

### Verwendung
```jsx
<Modal
  isOpen={isOpen}
  onClose={() => setIsOpen(false)}
  title="Dialog-Titel"
  size="md"
  footer={
    <div className="flex gap-2">
      <Button onClick={() => setIsOpen(false)}>Abbrechen</Button>
      <Button variant="primary">Speichern</Button>
    </div>
  }
>
  Dialog-Inhalt
</Modal>
```

### Props
- `isOpen` (boolean) - Öffnungszustand
- `onClose` (function) - Schließen-Handler
- `title` (string) - Dialog-Titel
- `size` - 'sm' | 'md' | 'lg' | 'xl' | 'full'
- `footer` (ReactNode) - Footer-Inhalt

---

## 💀 Skeleton

Loading-Skelette für bessere UX.

### Import
```jsx
import Skeleton, { SkeletonCard } from '@/Components/ui/Skeleton';
```

### Verwendung
```jsx
<Skeleton variant="rectangular" className="h-32 w-full" />
<Skeleton variant="circular" className="w-12 h-12" />
<Skeleton variant="text" className="w-3/4" />

{/* Vorgefertigte Karte */}
<SkeletonCard />
```

### Props
- `variant` - 'rectangular' | 'circular' | 'text'
- `className` (string) - CSS-Klassen für Größe

---

## 📊 StatCard

Statistik-Karten mit Trend-Anzeige.

### Import
```jsx
import StatCard from '@/Components/ui/StatCard';
```

### Verwendung
```jsx
<StatCard
  title="Anwesend Heute"
  value={15}
  subtitle="von 24 Mitarbeitern"
  icon={UserCheck}
  color="success"
  trend={8.2}
  loading={false}
/>
```

### Props
- `title` (string) - Titel
- `value` (string|number) - Hauptwert
- `subtitle` (string) - Untertitel
- `icon` (Component) - Lucide Icon
- `color` - 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'accent'
- `trend` (number) - Trend in % (positiv/negativ)
- `loading` (boolean) - Loading-State

---

## ⚠️ Alert

Warnungen und Hinweise.

### Import
```jsx
import Alert from '@/Components/ui/Alert';
```

### Verwendung
```jsx
<Alert 
  variant="warning" 
  title="Achtung"
  onClose={() => setShowAlert(false)}
>
  Dies ist eine Warnung!
</Alert>
```

### Props
- `variant` - 'success' | 'error' | 'warning' | 'info'
- `title` (string) - Titel
- `onClose` (function) - Schließen-Handler (optional)
- `className` (string) - Zusätzliche CSS-Klassen

---

## 📭 EmptyState

Leere Zustände mit Action.

### Import
```jsx
import EmptyState from '@/Components/ui/EmptyState';
```

### Verwendung
```jsx
<EmptyState
  icon={Calendar}
  title="Keine Daten"
  description="Es sind noch keine Einträge vorhanden."
  action="Erstellen"
  onAction={() => handleCreate()}
/>
```

### Props
- `icon` (Component) - Lucide Icon
- `title` (string) - Titel
- `description` (string) - Beschreibung
- `action` (string) - Action-Text
- `onAction` (function) - Action-Handler

---

## ⏳ LoadingSpinner

Lade-Spinner.

### Import
```jsx
import LoadingSpinner from '@/Components/ui/LoadingSpinner';
```

### Verwendung
```jsx
<LoadingSpinner size="lg" text="Lädt Daten..." />
```

### Props
- `size` - 'sm' | 'md' | 'lg' | 'xl'
- `text` (string) - Text unter Spinner

---

## 🎨 Farben

Verwenden Sie die vordefinierten Farben aus dem Design-System:

```jsx
// Tailwind-Klassen
className="bg-primary-500 text-white"
className="bg-success-100 text-success-700"
className="border-warning-200"

// Farbvarianten
primary-50 bis primary-900
secondary-50 bis secondary-900
accent-50 bis accent-900
success-50 bis success-900
warning-50 bis warning-900
error-50 bis error-900
neutral-50 bis neutral-900
```

---

## 🎯 Icons

Verwenden Sie Lucide React Icons:

```jsx
import { 
  Calendar, 
  Clock, 
  Users, 
  Download,
  // ... weitere Icons
} from 'lucide-react';

<Calendar className="w-5 h-5 text-primary-500" />
```

Alle verfügbaren Icons: https://lucide.dev/

---

## 🛠️ Utilities

### Formatters (`resources/js/utils/formatters.js`)

```jsx
import { 
  formatDate, 
  formatTime, 
  formatDuration,
  formatCurrency,
  formatNumber,
  formatPercentage,
  getRelativeTime,
  getInitials
} from '@/utils/formatters';

formatDate('2024-01-15') // "15.01.2024"
formatTime('14:30:00') // "14:30"
formatDuration(8.5) // "8h 30m"
formatCurrency(1234.56) // "1.234,56 €"
formatNumber(1234.567, 2) // "1.234,57"
formatPercentage(85.5) // "85,5%"
getRelativeTime('2024-01-15') // "vor 2 Tagen"
getInitials('Max', 'Mustermann') // "MM"
```

### Hooks (`resources/js/hooks/useDebounce.js`)

```jsx
import { useDebounce } from '@/hooks/useDebounce';

const [searchTerm, setSearchTerm] = useState('');
const debouncedSearch = useDebounce(searchTerm, 500);

useEffect(() => {
  // API-Call mit debouncedSearch
}, [debouncedSearch]);
```

---

## 📱 Responsive Design

Verwenden Sie Tailwind's responsive Prefixes:

```jsx
className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"
className="text-sm md:text-base lg:text-lg"
className="p-4 md:p-6 lg:p-8"
```

---

## ✨ Animationen

Vordefinierte Animationen:

```jsx
className="animate-fade-in"
className="animate-slide-up"
className="animate-scale-in"
className="animate-pulse-slow"
```