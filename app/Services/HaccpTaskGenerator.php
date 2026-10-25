<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpTaskGenerator
{
    public function generate(?Carbon $date = null): int
    {
        $date ??= today();
        $created = 0;
        $templates = DB::table('haccp_templates')->where('active', true)->get();

        foreach ($templates as $template) {
            if (!$this->isDue($template, $date)) continue;
            $schedule = json_decode($template->schedule ?: '{}', true) ?: [];
            $times = array_values(array_filter($schedule['times'] ?? ['12:00']));
            if (!$times) $times = ['12:00'];

            foreach ($times as $time) {
                $dueAt = Carbon::parse($date->toDateString().' '.$time);
                $windowBefore = max(0, (int)($schedule['window_before_minutes'] ?? 120));
                $dueFrom = $dueAt->copy()->subMinutes($windowBefore);
                $inserted = DB::table('haccp_tasks')->insertOrIgnore([
                    'template_id' => $template->id,
                    'device_id' => $template->device_id,
                    'haccp_role_id' => $template->haccp_role_id,
                    'title' => $template->name,
                    'due_from' => $dueFrom,
                    'due_at' => $dueAt,
                    'status' => 'open',
                    'snapshot' => json_encode([
                        'name' => $template->name,
                        'category' => $template->category,
                        'criticality' => $template->criticality ?? 'normal',
                        'template_version' => (int)($template->version ?? 1),
                        'result_type' => $template->result_type,
                        'configuration' => json_decode($template->configuration ?: '{}', true),
                        'requires_corrective_action' => (bool)$template->requires_corrective_action,
                        'schedule' => $schedule,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created += (int)$inserted;
            }
        }
        return $created;
    }

    private function isDue(object $template, Carbon $date): bool
    {
        $schedule = json_decode($template->schedule ?: '{}', true) ?: [];
        $created = Carbon::parse($template->created_at)->startOfDay();
        if ($date->copy()->startOfDay()->lt($created)) return false;

        return match ($template->frequency) {
            'daily' => true,
            'weekly' => in_array($date->dayOfWeekIso, array_map('intval', $schedule['weekdays'] ?? [1]), true),
            'monthly' => $date->day === min((int)($schedule['day'] ?? 1), $date->daysInMonth),
            'interval' => $created->diffInDays($date->copy()->startOfDay()) % max(1, (int)($schedule['days'] ?? 1)) === 0,
            default => false,
        };
    }
}
