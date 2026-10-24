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
            $times = $schedule['times'] ?? ['12:00'];
            if (!$times) $times = ['12:00'];

            foreach ($times as $time) {
                $dueAt = Carbon::parse($date->toDateString().' '.$time);
                $dueFrom = $dueAt->copy()->subMinutes((int) ($schedule['window_before_minutes'] ?? 120));
                $exists = DB::table('haccp_tasks')->where('template_id', $template->id)->where('due_at', $dueAt)->exists();
                if ($exists) continue;

                DB::table('haccp_tasks')->insert([
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
                        'template_version' => (int) ($template->version ?? 1),
                        'result_type' => $template->result_type,
                        'configuration' => json_decode($template->configuration ?: '{}', true),
                        'requires_corrective_action' => (bool) $template->requires_corrective_action,
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $created++;
            }
        }
        return $created;
    }

    private function isDue(object $template, Carbon $date): bool
    {
        $schedule = json_decode($template->schedule ?: '{}', true) ?: [];
        return match ($template->frequency) {
            'daily' => true,
            'weekly' => in_array($date->dayOfWeekIso, array_map('intval', $schedule['weekdays'] ?? [1]), true),
            'monthly' => $date->day === (int) ($schedule['day'] ?? 1),
            'interval' => $date->diffInDays(Carbon::parse($template->created_at)) % max(1, (int) ($schedule['days'] ?? 1)) === 0,
            default => false,
        };
    }
}
