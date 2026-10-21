<?php

namespace App\Services\Info;

use App\Models\InfoDisplay;
use App\Models\InfoIntegration;
use App\Models\InfoItem;
use App\Models\InfoReservation;
use App\Models\Shift;
use Carbon\CarbonImmutable;

class DisplayFeed
{
    public function snapshot(InfoDisplay $display): array
    {
        $now = now();
        $today = $now->copy()->timezone('Europe/Berlin')->toDateString();
        $items = InfoItem::with('employee:id,first_name,last_name')->where('active', true)
            ->where(fn ($q) => $q->whereNull('display_id')->orWhere('display_id', $display->id))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(function ($q) use ($now) {
                $q->where('kind', 'event')->where('starts_at', '<=', $now->copy()->addDays(14))
                    ->orWhere(fn ($q) => $q->where('kind', '!=', 'event')->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now)));
            })->whereNull('completed_at')->orderByDesc('priority')->orderBy('sort_order')->orderBy('id')->get();
        $reservations = InfoReservation::where('starts_at', '>=', $now->copy()->timezone('Europe/Berlin')->startOfDay()->utc())
            ->where('starts_at', '<', $now->copy()->timezone('Europe/Berlin')->addDay()->startOfDay()->utc())
            ->whereNotIn('status', ['cancelled', 'canceled', 'deleted', 'no_show', 'noshow', 'removed'])->orderBy('starts_at')->get()
            ->map(fn ($r) => ['id' => $r->id, 'starts_at' => CarbonImmutable::parse($r->getRawOriginal('starts_at'), 'UTC')->toIso8601String(), 'people' => $r->people,
                'guest_name' => $display->show_guest_names ? $r->guest_name : null, 'tables' => $r->tables, 'status' => $r->status]);
        $shifts = Shift::with(['employee:id,first_name,last_name', 'department:id,name', 'shiftType'])
            ->whereDate('shift_date', $today)->when($display->department_id, fn ($q) => $q->where('department_id', $display->department_id))
            ->get()->map(fn ($s) => ['id' => $s->id, 'name' => $s->employee?->full_name,
                'department' => $s->department?->name, 'start' => $s->start_time ?: $s->shiftType?->default_start,
                'end' => $s->end_time ?: $s->shiftType?->default_end]);
        $integration = InfoIntegration::settings();

        return ['display' => ['name' => $display->name, 'slug' => $display->slug], 'date' => $today,
            'items' => $items->map(function ($i) use ($display) {
                $data = $i->toArray();
                if ($i->file_path) {
                    $data['url'] = '/display/'.$display->slug.'/media/'.$i->id;
                }

                return $data;
            }), 'reservations' => $reservations, 'shifts' => $shifts,
            'resmio' => ['enabled' => $integration->enabled, 'last_success_at' => $integration->last_success_at?->toIso8601String(), 'failed' => (bool) $integration->last_error]];
    }
}
