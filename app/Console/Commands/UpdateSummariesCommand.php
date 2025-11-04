<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{
    Employee,
    DailyTimeSummary,
    MonthlyTimeSummary,
    TimeEntry
};
use Carbon\Carbon;

class UpdateSummariesCommand extends Command
{
    /**
     * Der Konsolenbefehl-Name.
     */
    protected $signature = 'summary:update {--date=}';

    /**
     * Beschreibung des Befehls.
     */
    protected $description = 'Aktualisiert Tages- und Monatszusammenfassungen für alle Mitarbeiter.';

    /**
     * Ausführung des Befehls.
     */
    public function handle(): int
    {
        $dateOption = $this->option('date');
        $targetDate = $dateOption ? Carbon::parse($dateOption) : Carbon::yesterday();

        $this->info("⏳ Aktualisiere Zusammenfassungen für {$targetDate->toDateString()} …");

        $employees = Employee::where('active', true)->get();

        foreach ($employees as $employee) {
            // Tageszusammenfassung neu berechnen
            $daily = DailyTimeSummary::updateForDay($employee->id, $targetDate);
            $this->line("✅ {$employee->full_name}: Tagesdaten aktualisiert ({$daily->total_hours} Std)");

            // Monatszusammenfassung neu berechnen
            MonthlyTimeSummary::updateForMonth($employee->id, $targetDate);
        }

        $this->info('🎯 Alle Zusammenfassungen erfolgreich aktualisiert.');

        return Command::SUCCESS;
    }
}
