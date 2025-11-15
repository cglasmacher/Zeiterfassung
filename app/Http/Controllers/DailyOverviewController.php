<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DailyOverviewController extends Controller
{
    /**
     * Get all time entries for today, grouped by cash payment status
     */
    public function index(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        // Get all completed time entries for the day
        $allEntries = TimeEntry::with(['employee.departments'])
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->orderBy('clock_in', 'desc')
            ->get();

        // Separate cash payment employees
        $cashPaymentEntries = $allEntries->filter(function ($entry) {
            return $entry->employee && $entry->employee->cash_payment;
        })->values();

        $regularEntries = $allEntries->filter(function ($entry) {
            return !$entry->employee || !$entry->employee->cash_payment;
        })->values();

        // Count unpaid cash entries
        $unpaidCount = $cashPaymentEntries->filter(function ($entry) {
            return is_null($entry->paid_out_at);
        })->count();

        return response()->json([
            'date' => $date,
            'all_entries' => $allEntries,
            'cash_payment_entries' => $cashPaymentEntries,
            'regular_entries' => $regularEntries,
            'unpaid_count' => $unpaidCount,
            'total_cash_amount' => $cashPaymentEntries->sum('gross_wage'),
        ]);
    }

    /**
     * Mark a time entry as paid out
     */
    public function markAsPaid($id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);

        if (!$entry->clock_out) {
            return response()->json([
                'error' => 'Schicht muss abgeschlossen sein'
            ], 422);
        }

        $entry->paid_out_at = now();
        $entry->save();

        return response()->json([
            'message' => 'Als ausgezahlt markiert',
            'entry' => $entry,
        ]);
    }

    /**
     * Unmark a time entry as paid out
     */
    public function unmarkAsPaid($id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);

        $entry->paid_out_at = null;
        $entry->save();

        return response()->json([
            'message' => 'Auszahlung zurückgesetzt',
            'entry' => $entry,
        ]);
    }

    /**
     * Generate shift end report PDF
     */
    public function generateShiftEndReport(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        // Get all cash payment entries for the day
        $entries = TimeEntry::with(['employee.departments'])
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->whereHas('employee', function ($query) {
                $query->where('cash_payment', true);
            })
            ->orderBy('clock_in')
            ->get();

        if ($entries->isEmpty()) {
            return response()->json([
                'error' => 'Keine Barauszahlungen für diesen Tag gefunden'
            ], 404);
        }

        // Group by department
        $groupedByDepartment = $entries->groupBy(function ($entry) {
            $departments = $entry->employee->departments;
            if ($departments->isEmpty()) {
                return 'Ohne Abteilung';
            }
            return $departments->first()->name;
        });

        $totalAmount = $entries->sum('gross_wage');
        $unpaidCount = $entries->filter(fn($e) => is_null($e->paid_out_at))->count();

        $data = [
            'date' => Carbon::parse($date)->format('d.m.Y'),
            'time' => now()->format('H:i'),
            'grouped_entries' => $groupedByDepartment,
            'total_amount' => $totalAmount,
            'unpaid_count' => $unpaidCount,
        ];

        $pdf = Pdf::loadView('exports.shift-end-report', $data);

        // Reset all paid_out_at timestamps after generating report
        if ($request->input('reset_paid_status', false)) {
            TimeEntry::whereBetween('clock_in', [$startOfDay, $endOfDay])
                ->whereNotNull('paid_out_at')
                ->update(['paid_out_at' => null]);
        }

        return $pdf->download('Schichtende_' . Carbon::parse($date)->format('Y-m-d') . '.pdf');
    }
}