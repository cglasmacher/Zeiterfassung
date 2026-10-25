<?php

namespace Tests\Support;

trait ResmioTestDatabase
{
    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000003_create_employees_table.php',
            'database/migrations/0001_01_01_000004_create_departments_table.php',
            'database/migrations/0001_01_01_000006_create_shift_types_table.php',
            'database/migrations/0001_01_01_000007_create_shifts_table.php',
            'database/migrations/2026_10_21_100000_create_infosystem_tables.php',
            'database/migrations/2026_10_21_110000_add_workspace_access.php',
            'database/migrations/2026_10_25_220000_create_reservation_floor_plans.php',
            'database/migrations/2026_10_25_230000_create_reservation_book.php',
            'database/migrations/2026_10_25_235000_add_resmio_reservation_sync.php',
        ]];
    }
}
