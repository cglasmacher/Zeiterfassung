# Installation Notes for Barauszahlung Feature

## Required Package Installation

Before running the application, you need to install the DomPDF package for PDF generation:

```bash
composer require barryvdh/laravel-dompdf
```

## Database Migrations

Run the following command to apply the new database migrations:

```bash
php artisan migrate
```

This will add:
- `cash_payment` column to `employees` table
- `paid_out_at` column to `time_entries` table

## Features Added

1. **Employee Cash Payment Checkbox**: In Settings > Mitarbeiter, you can now mark employees for cash payment (Barauszahlung)

2. **Daily Overview Enhancements**:
   - Two tabs: "Alle Mitarbeiter" and "Barauszahlung"
   - Cash payment employees can be marked as paid out
   - Shift-end report generation with PDF download

3. **Shift End Report**:
   - Groups employees by department
   - Shows total cash payment amount
   - Warns if employees haven't been marked as paid
   - Resets payment status after report generation

## Usage

1. Mark employees for cash payment in Settings > Mitarbeiter
2. Go to Tagesübersicht (Daily Overview)
3. Switch to "Barauszahlung" tab
4. Mark employees as paid out using the "Auszahlen" button
5. Click "Schichtende" to generate and download the PDF report