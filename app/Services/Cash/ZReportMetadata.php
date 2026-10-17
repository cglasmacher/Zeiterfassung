<?php

namespace App\Services\Cash;

use Carbon\CarbonImmutable;

class ZReportMetadata
{
    public function parse(string $text): array
    {
        $result = ['report_number' => null, 'register_name' => null, 'reported_at' => null,
            'business_date' => null, 'assignment_source' => 'pending'];
        // Only the known original Vectron Z1 layout is dated automatically.
        // Last reset dates and dates in email headers/file names are never used.
        if (! preg_match('/Finanzbericht\s*<\s*Z1\s*>/iu', $text)) {
            return $result;
        }
        if (preg_match('/Finanzbericht\s+1\s+(\d{1,20})\b/u', $text, $number)) {
            $result['report_number'] = $number[1];
        }
        preg_match_all('/(?:^|\n)\s*(Kasse[^\n]{0,90})\n\s*(\d{2}\.\d{2}\.\d{2}(?:\d{2})?)\s+(\d{2}:\d{2})\b/u', str_replace("\r", '', $text), $matches, PREG_SET_ORDER);
        $timestamps = [];
        foreach ($matches as $match) {
            try {
                $format = strlen($match[2]) === 8 ? 'd.m.y H:i' : 'd.m.Y H:i';
                $at = CarbonImmutable::createFromFormat('!'.$format, $match[2].' '.$match[3], 'Europe/Berlin');
                if (! $at || $at->format($format) !== $match[2].' '.$match[3]) {
                    continue;
                }
                $timestamps[$at->toIso8601String()] = [$at, trim($match[1])];
            } catch (\Throwable) {
                continue;
            }
        }
        if (count($timestamps) !== 1) {
            return $result;
        }
        [$at, $register] = array_values($timestamps)[0];
        $cutoff = max(0, min(23, (int) config('cash.z_reports.business_day_starts_at', 6)));
        $result['register_name'] = $register;
        $result['reported_at'] = $at->utc()->format('Y-m-d H:i:s');
        $result['business_date'] = ($at->hour < $cutoff ? $at->subDay() : $at)->toDateString();
        $result['assignment_source'] = 'report_time';

        return $result;
    }
}
