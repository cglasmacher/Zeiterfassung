# Troubleshooting: Arbeitszeit und Lohn werden nicht berechnet

## Problem
Nach dem Ausstempeln zeigt das System 0.00 für Arbeitszeit und Lohn an.

## Lösungsschritte

### 1. Überprüfen Sie, ob die Migrationen ausgeführt wurden

```bash
php artisan migrate
```

### 2. Überprüfen Sie die Datenbank-Spalten

```bash
php artisan db:check-columns
```

Wenn Spalten fehlen, führen Sie aus:
```bash
php artisan migrate --force
```

### 3. Überprüfen Sie, ob der Mitarbeiter einen Stundenlohn hat

- Gehen Sie zu Settings > Mitarbeiter
- Öffnen Sie den Mitarbeiter
- Stellen Sie sicher, dass "Stundenlohn (€)" ausgefüllt ist (z.B. 15.00)

### 4. Neuberechnung für bestehende Einträge

Für heute:
```bash
php artisan wages:recalculate --date=2025-01-XX
```

Für alle Einträge:
```bash
php artisan wages:recalculate
```

### 5. Testen Sie mit einem neuen Ein-/Ausstempeln

1. Stempeln Sie einen Mitarbeiter ein
2. Warten Sie ein paar Minuten
3. Stempeln Sie aus
4. Überprüfen Sie die Tagesübersicht

### 6. Debug-Informationen abrufen

Rufen Sie diese URL auf (ersetzen Sie {id} mit der Entry-ID):
```
http://localhost:8090/api/daily-overview/debug-entry/{id}
```

Dies zeigt Ihnen:
- Ob der Mitarbeiter einen Stundenlohn hat
- Die berechneten Werte
- Die gespeicherten Werte

### 7. Manuelle SQL-Überprüfung

Öffnen Sie Ihre Datenbank und führen Sie aus:

```sql
-- Überprüfen Sie die Spalten
SHOW COLUMNS FROM time_entries LIKE '%hours%';
SHOW COLUMNS FROM time_entries LIKE '%wage%';

-- Überprüfen Sie einen Eintrag
SELECT id, employee_id, clock_in, clock_out, break_minutes, total_hours, gross_wage
FROM time_entries
WHERE id = {ihre_entry_id};

-- Überprüfen Sie den Stundenlohn des Mitarbeiters
SELECT id, first_name, last_name, hourly_rate, cash_payment
FROM employees
WHERE id = {ihre_employee_id};
```

## Häufige Ursachen

1. **Migrationen nicht ausgeführt**: Die Spalten `total_hours` und `gross_wage` existieren nicht
2. **Kein Stundenlohn**: Der Mitarbeiter hat keinen `hourly_rate` gesetzt
3. **Cache-Problem**: Browser-Cache oder Laravel-Cache verhindert Updates
4. **Alte Einträge**: Einträge vor dem Code-Update müssen neu berechnet werden

## Schnellreparatur

Führen Sie einfach aus:
```bash
fix-database.bat
```

Dies führt automatisch alle notwendigen Schritte aus.