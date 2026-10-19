<?php

namespace App\Services\Cash;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class CashDocument
{
    public function describe(string $kind, int $id): array
    {
        abort_unless(in_array($kind, ['handover', 'day'], true), 404);
        if ($kind === 'handover') {
            $record = DB::table('cash_handovers')->find($id);
            abort_unless($record, 404);
            $day = DB::table('cash_days')->find($record->cash_day_id);
            // IDs preserve the stable order of confirmed handovers for this business day.
            $sequence = DB::table('cash_handovers')->where('cash_day_id', $day->id)->where('id', '<=', $id)->count();
            $suffix = '-Schicht'.($sequence > 1 ? '-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT) : '');
        } else {
            $day = DB::table('cash_days')->where('id', $id)->where('status', 'finalized')->first();
            abort_unless($day && $day->snapshot, 404);
            $suffix = '-Tag';
        }
        $date = $day->business_date;
        $filename = str_replace('-', '.', $date).$suffix.'.pdf';

        return ['document_key' => $kind.'-'.$id, 'kind' => $kind, 'id' => $id,
            'business_date' => $date, 'filename' => $filename,
            'relative_path' => substr($date, 0, 4).'/'.substr($date, 5, 2).'/'.$filename,
            'download_path' => '/api/integrations/cash-documents/'.$kind.'/'.$id.'/pdf',
            'ack_path' => '/api/integrations/cash-documents/'.$kind.'/'.$id.'/ack'];
    }

    public function render(string $kind, int $id): string
    {
        $this->describe($kind, $id);
        if ($kind === 'day') {
            $day = DB::table('cash_days')->find($id);

            return Pdf::loadView('cash.day', ['snapshot' => json_decode($day->snapshot, true)])->output();
        }
        $handover = DB::table('cash_handovers')->find($id);
        $day = DB::table('cash_days')->find($handover->cash_day_id);

        return Pdf::loadView('cash.handover', ['day' => $day, 'handover' => $handover,
            'snapshot' => json_decode($handover->snapshot, true)])->output();
    }
}
