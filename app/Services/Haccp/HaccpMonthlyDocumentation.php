<?php

namespace App\Services\Haccp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpMonthlyDocumentation
{
    public function build(int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $tasks = DB::table('haccp_tasks as t')
            ->join('haccp_templates as p', 'p.id', '=', 't.template_id')
            ->leftJoin('haccp_devices as d', 'd.id', '=', 't.device_id')
            ->whereBetween('t.due_at', [$from, $to])
            ->select('t.*', 'p.category', 'p.result_type', 'p.name as template_name', 'd.name as device_name', 'd.location', 'd.min_value', 'd.max_value', 'd.unit')
            ->orderBy('t.due_at')->get();

        $taskIds = $tasks->pluck('id');
        $results = $taskIds->isEmpty() ? collect() : DB::table('haccp_task_results as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.completed_by')
            ->whereIn('r.task_id', $taskIds)
            ->select('r.*', 'u.name as completed_by_name')->get()->keyBy('task_id');

        $resultIds = $results->pluck('id');
        $deviations = $resultIds->isEmpty() ? collect() : DB::table('haccp_deviations as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.resolved_by')
            ->whereIn('d.task_result_id', $resultIds)
            ->select('d.*', 'u.name as resolved_by_name')->get()->keyBy('task_result_id');

        $temperature = [];
        foreach ($tasks->where('result_type', 'temperature') as $task) {
            $result = $results->get($task->id);
            $day = Carbon::parse($task->due_at)->day;
            $device = $task->device_name ?: $task->title;
            $temperature[$device]['meta'] ??= [
                'location' => $task->location,
                'min' => $task->min_value,
                'max' => $task->max_value,
                'unit' => $task->unit ?: '°C',
            ];
            $temperature[$device]['days'][$day][] = [
                'value' => $result?->numeric_value,
                'within_limits' => $result?->within_limits,
                'completed_at' => $result?->completed_at,
                'completed_by' => $result?->completed_by_name,
                'missing' => ! $result,
                'deviation_id' => $result && $deviations->has($result->id) ? $deviations->get($result->id)->id : null,
            ];
        }

        $deviationRows = [];
        foreach ($deviations as $deviation) {
            $result = $results->firstWhere('id', $deviation->task_result_id);
            $task = $result ? $tasks->firstWhere('id', $result->task_id) : null;
            if (! $task) continue;
            $deviationRows[] = [
                'id' => $deviation->id,
                'date' => Carbon::parse($result->completed_at)->toDateString(),
                'control' => $task->title,
                'device' => $task->device_name,
                'value' => $result->numeric_value,
                'description' => $deviation->description,
                'corrective_action' => $deviation->corrective_action,
                'resolved_at' => $deviation->resolved_at,
                'resolved_by' => $deviation->resolved_by_name,
            ];
        }

        $completed = $results->count();
        $total = $tasks->count();
        $missing = max(0, $total - $completed);

        return [
            'period' => ['year' => $year, 'month' => $month, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $to->day],
            'statistics' => [
                'total' => $total,
                'completed' => $completed,
                'missing' => $missing,
                'completion_rate' => $total ? round($completed / $total * 100, 1) : 100,
                'deviations' => count($deviationRows),
                'open_deviations' => collect($deviationRows)->whereNull('resolved_at')->count(),
            ],
            'temperature_matrix' => $temperature,
            'deviations' => $deviationRows,
            'missing_controls' => $tasks->filter(fn ($task) => ! $results->has($task->id))->map(fn ($task) => [
                'id' => $task->id,
                'due_at' => $task->due_at,
                'title' => $task->title,
                'device' => $task->device_name,
                'category' => $task->category,
            ])->values()->all(),
        ];
    }
}
