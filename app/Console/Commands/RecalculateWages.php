<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimeEntry;
use App\Models\BreakRule;

class RecalculateWages extends Command
{
    protected $signature = 'wages:recalculate {--date= : Specific date to recalculate (Y-m-d)}';
    protected $description = 'Recalculate wages for time entries that have clock_out but missing total_hours or gross_wage';

    public function handle()
    {
        $date = $this->option('date');
        
        $query = TimeEntry::with('employee')
            ->whereNotNull('clock_out')
            ->where(function($q) {
                $q->whereNull('total_hours')
                  ->orWhereNull('gross_wage')
                  ->orWhere('gross_wage', 0);
            });

        if ($date) {
            $query->whereDate('clock_in', $date);
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            $this->info('No entries found that need recalculation.');
            return 0;
        }

        $this->info("Found {$entries->count()} entries to recalculate...");
        
        $bar = $this->output->createProgressBar($entries->count());
        $bar->start();

        foreach ($entries as $entry) {
            $clockIn = $entry->clock_in;
            $clockOut = $entry->clock_out;

            if ($clockOut->lt($clockIn)) {
                $clockOut = $clockOut->copy()->addDay();
            }

            $totalMinutes = $clockOut->diffInMinutes($clockIn);
            $breakMinutes = $entry->break_minutes ?? BreakRule::calculateBreakForHours($totalMinutes / 60);
            
            $workMinutes = max(0, $totalMinutes - $breakMinutes);
            $workHours = $workMinutes / 60;
            
            $hourlyRate = $entry->override_hourly_rate ?? $entry->employee->hourly_rate ?? 0;
            $grossWage = $workHours * $hourlyRate;

            $entry->update([
                'break_minutes' => $breakMinutes,
                'total_hours' => round($workHours, 2),
                'gross_wage' => round($grossWage, 2),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Wage recalculation completed successfully!');

        return 0;
    }
}