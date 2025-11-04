<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{
    Employee,
    MonthlyTimeSummary
};
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExportSummariesCommand extends Command
{
    protected $signature = 'summary:export {--month=} {--year=}';
    protected $description = 'Exportiert Monatszusammenfassungen im CSV-Format für Lexware Lohn+Gehalt';

    public function handle(): int
    {
        $month = $this->option('month') ?? Carbon::now()->subMonth()->month;
        $year  = $this->option('year') ?? Carbon::now()->year;

        $this->info("📦 Exportiere Arbeitszeiten für {$month}/{$year} …");

        $employees = Employee::where('active', true)->get();
        $exportDir = storage_path('app/exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0775, true);
        }

        $filename = "{$exportDir}/arbeitszeiten_{$year}_{$month}.csv";
        $handle = fopen($filename, 'w');

        // CSV-Kopf (Lexware-kompatibel)
        fputcsv($handle, [
            'Personalnummer',
            'Name',
            'Monat',
            'Jahr',
            'Gearbeitete Stunden',
            'Pausen (Min)',
            'Überstunden',
            'Arbeitstage',
        ], ';');

        foreach ($employees as $employee) {
            $summary = MonthlyTimeSummary::where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (!$summary) {
                $this->line("⚠️ Keine Daten für {$employee->full_name}");
                continue;
            }

            // Lexware-Datensatz
            fputcsv($handle, [
                $employee->id, // kann später mit echter Personalnummer ersetzt werden
                $employee->full_name,
                $month,
                $year,
                number_format($summary->total_hours, 2, ',', ''),
                number_format($summary->total_break_minutes, 0, ',', ''),
                number_format($summary->overtime_hours, 2, ',', ''),
                $summary->working_days,
            ], ';');
        }

        fclose($handle);
        $this->info("✅ Export abgeschlossen: {$filename}");

        return Command::SUCCESS;
    }
}
