<?php

namespace Database\Seeders;

use App\Models\BreakRule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Retain the existing initial pause configuration without creating fictitious staff.
        foreach ([['name' => 'Ab 6 Stunden', 'min_hours' => 6, 'break_minutes' => 30], ['name' => 'Ab 9 Stunden', 'min_hours' => 9, 'break_minutes' => 45]] as $rule) {
            BreakRule::firstOrCreate(['name' => $rule['name']], [...$rule, 'active' => true]);
        }
    }
}
