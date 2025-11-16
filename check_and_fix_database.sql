-- Check if columns exist in time_entries table
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'time_entries'
AND COLUMN_NAME IN ('total_hours', 'gross_wage', 'paid_out_at');

-- If columns don't exist, add them (uncomment and run if needed)
-- ALTER TABLE time_entries ADD COLUMN total_hours DECIMAL(6,2) NULL AFTER break_minutes;
-- ALTER TABLE time_entries ADD COLUMN gross_wage DECIMAL(8,2) NULL AFTER total_hours;
-- ALTER TABLE time_entries ADD COLUMN paid_out_at TIMESTAMP NULL AFTER is_manual;

-- Check if cash_payment column exists in employees table
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'employees'
AND COLUMN_NAME = 'cash_payment';

-- If column doesn't exist, add it (uncomment and run if needed)
-- ALTER TABLE employees ADD COLUMN cash_payment TINYINT(1) NOT NULL DEFAULT 0 AFTER active;