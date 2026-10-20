<?php

namespace App\Services\Cash;

use Carbon\CarbonImmutable;

class ZReportMetadata
{
    public function parse(string $text): array
    {
        $result = ['report_number' => null, 'register_name' => null, 'reported_at' => null,
            'business_date' => null, 'assignment_source' => 'pending'];
        // PDF extractors can put footer fields on one line, several lines or different pages.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        $text = preg_replace('/[\s\p{Z}]+/u', ' ', $text);
        if (! preg_match('/Finanzbericht\s*<\s*Z\s*1\s*>/iu', $text)) {
            return $result;
        }
        // Only use the financial summary, never later operator-report footers.
        $summary = preg_split('/Bediener\s*(?:\([^)]*\))?\s*<\s*Z\s*1\s*>/iu', $text, 2)[0];
        if (preg_match('/Finanzbericht\s*1\s*(\d{1,20})\b/iu', $summary, $number)) {
            $result['report_number'] = $number[1];
        }
        preg_match_all('/(Kasse\s*[\p{L}\p{N} #_.-]{0,70}?)\s*(\d{2}\.\d{2}\.(?:\d{4}|\d{2}))\s*(\d{2}:\d{2}(?::\d{2})?)(?!\d)/iu', $summary, $matches, PREG_SET_ORDER);
        $timestamps = [];
        foreach ($matches as $match) {
            // The reset date near the top is not the date this report was produced.
            if (preg_match('/Nullst|Letzte/iu', $match[1])) {
                continue;
            }
            try {
                $format = (strlen($match[2]) === 8 ? 'd.m.y' : 'd.m.Y').(strlen($match[3]) === 5 ? ' H:i' : ' H:i:s');
                $at = CarbonImmutable::createFromFormat('!'.$format, $match[2].' '.$match[3], 'Europe/Berlin');
                if (! $at || $at->format($format) !== $match[2].' '.$match[3]) {
                    continue;
                }
                $register = trim(preg_replace('/^.*(?=Kasse)/iu', '', $match[1]));
                $timestamps[$at->toIso8601String()] = [$at, $register];
            } catch (\Throwable) {
                continue;
            }
        }
        if (count($timestamps) !== 1) {
            return $result;
        }
        [$at, $register] = array_values($timestamps)[0];
        $until = (string) config('cash.z_reports.previous_day_until', '11:00');
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $until)) {
            throw new \RuntimeException('CASH_Z_REPORT_PREVIOUS_DAY_UNTIL muss HH:MM enthalten.');
        }
        $result['register_name'] = $register;
        $result['reported_at'] = $at->utc()->format('Y-m-d H:i:s');
        $result['business_date'] = ($at->format('H:i:s') <= $until.':00' ? $at->subDay() : $at)->toDateString();
        $result['assignment_source'] = 'report_time';

        return $result;
    }
}
