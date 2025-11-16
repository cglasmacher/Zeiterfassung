<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckDatabase extends Command
{
    protected $signature = 'db:check-columns';
    protected $description = 'Check if required database columns exist';

    public function handle()
    {
        $this->info('Checking database columns...');
        
        // Check time_entries table
        $this->info("\nChecking time_entries table:");
        $timeEntriesColumns = ['total_hours', 'gross_wage', 'paid_out_at'];
        foreach ($timeEntriesColumns as $column) {
            if (Schema::hasColumn('time_entries', $column)) {
                $this->info("✓ Column '{$column}' exists");
            } else {
                $this->error("✗ Column '{$column}' is MISSING!");
            }
        }
        
        // Check employees table
        $this->info("\nChecking employees table:");
        if (Schema::hasColumn('employees', 'cash_payment')) {
            $this->info("✓ Column 'cash_payment' exists");
        } else {
            $this->error("✗ Column 'cash_payment' is MISSING!");
        }
        
        // Check for entries with zero wages
        $this->info("\nChecking for entries with zero wages:");
        $zeroWageCount = DB::table('time_entries')
            ->whereNotNull('clock_out')
            ->where(function($q) {
                $q->whereNull('gross_wage')
                  ->orWhere('gross_wage', 0);
            })
            ->count();
        
        if ($zeroWageCount > 0) {
            $this->warn("Found {$zeroWageCount} entries with zero or null wages");
            $this->info("Run 'php artisan wages:recalculate' to fix them");
        } else {
            $this->info("✓ No entries with zero wages found");
        }
        
        $this->info("\nDone!");
        
        return 0;
    }
}