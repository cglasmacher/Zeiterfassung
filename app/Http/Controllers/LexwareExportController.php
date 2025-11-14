<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{TimeEntry, Employee};
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class LexwareExportController extends Controller
{
    public function export(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,datev',
        ]);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $format = $data['format'];

        // Hole nur Aushilfen/Teilzeitlöhner
        $employees = Employee::where('employment_type', 'temporary')
            ->where('active', true)
            ->get();

        $exportData = [];

        foreach ($employees as $employee) {
            // Hole alle Zeiteinträge für diesen Mitarbeiter im Zeitraum
            $entries = TimeEntry::where('employee_id', $employee->id)
                ->whereNotNull('clock_out')
                ->whereBetween('clock_in', [$startDate, $endDate])
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            // Summiere Stunden und Lohn
            $totalHours = $entries->sum(function($entry) {
                return $entry->total_hours ?? 0;
            });

            $totalWage = $entries->sum(function($entry) {
                return $entry->gross_wage ?? 0;
            });

            $exportData[] = [
                'employee_number' => $employee->employee_number ?? '',
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'hours' => number_format($totalHours, 2, ',', ''),
                'hourly_rate' => number_format($employee->hourly_rate ?? 0, 2, ',', ''),
                'gross_wage' => number_format($totalWage, 2, ',', ''),
                'date_from' => $startDate->format('d.m.Y'),
                'date_to' => $endDate->format('d.m.Y'),
            ];
        }

        if ($format === 'csv') {
            return $this->exportCSV($exportData, $startDate, $endDate);
        } else {
            return $this->exportDATEV($exportData, $startDate, $endDate);
        }
    }

    private function exportCSV($data, $startDate, $endDate)
    {
        $filename = 'lexoffice_export_' . $startDate->format('Y-m-d') . '_bis_' . $endDate->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM für Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($file, [
                'Mitarbeiternummer',
                'Nachname',
                'Vorname',
                'Stunden',
                'Stundenlohn',
                'Bruttolohn',
                'Datum Von',
                'Datum Bis'
            ], ';');

            // Daten
            foreach ($data as $row) {
                fputcsv($file, [
                    $row['employee_number'],
                    $row['last_name'],
                    $row['first_name'],
                    $row['hours'],
                    $row['hourly_rate'],
                    $row['gross_wage'],
                    $row['date_from'],
                    $row['date_to'],
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    private function exportDATEV($data, $startDate, $endDate)
    {
        $filename = 'datev_export_' . $startDate->format('Y-m-d') . '_bis_' . $endDate->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=ISO-8859-1',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // DATEV Header (vereinfacht)
            fputcsv($file, [
                'EXTF',
                '510',
                '21',
                'Lohnbuchungen',
                '',
                '',
                '',
                '',
                '',
                '',
                $startDate->format('Ymd') . $endDate->format('Ymd'),
            ], ';');

            // Spaltenüberschriften
            fputcsv($file, [
                'Personalnummer',
                'Nachname',
                'Vorname',
                'Stunden',
                'Stundensatz',
                'Betrag',
                'Datum von',
                'Datum bis',
            ], ';');

            // Daten
            foreach ($data as $row) {
                $line = [
                    $row['employee_number'],
                    mb_convert_encoding($row['last_name'], 'ISO-8859-1', 'UTF-8'),
                    mb_convert_encoding($row['first_name'], 'ISO-8859-1', 'UTF-8'),
                    $row['hours'],
                    $row['hourly_rate'],
                    $row['gross_wage'],
                    $row['date_from'],
                    $row['date_to'],
                ];
                fputcsv($file, $line, ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        // Hole nur Aushilfen
        $employees = Employee::where('employment_type', 'temporary')
            ->where('active', true)
            ->with('timeEntries')
            ->get();

        $previewData = [];
        $totalHours = 0;
        $totalWage = 0;

        foreach ($employees as $employee) {
            $entries = TimeEntry::where('employee_id', $employee->id)
                ->whereNotNull('clock_out')
                ->whereBetween('clock_in', [$startDate, $endDate])
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $hours = $entries->sum(function($entry) {
                return $entry->total_hours ?? 0;
            });

            $wage = $entries->sum(function($entry) {
                return $entry->gross_wage ?? 0;
            });

            $totalHours += $hours;
            $totalWage += $wage;

            $previewData[] = [
                'employee' => $employee,
                'entries_count' => $entries->count(),
                'total_hours' => $hours,
                'total_wage' => $wage,
            ];
        }

        return response()->json([
            'preview' => $previewData,
            'summary' => [
                'total_employees' => count($previewData),
                'total_hours' => $totalHours,
                'total_wage' => $totalWage,
            ],
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y'),
            ],
        ]);
    }
}