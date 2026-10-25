<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\HaccpTaskGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileHaccpController extends Controller
{
    public function today(Request $request, HaccpTaskGenerator $generator)
    {
        $generator->generate();

        $tasks = $this->query($request)
            ->orderByRaw("CASE WHEN t.status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('t.due_at')
            ->get()
            ->map(fn ($task) => $this->serializeTask($task));

        $open = $tasks->where('status', 'open');
        $now = now();

        return response()->json([
            'summary' => [
                'total' => $tasks->count(),
                'open' => $open->count(),
                'overdue' => $open->filter(fn ($task) => !empty($task['due_at']) && $now->gt($task['due_at']))->count(),
                'critical_overdue' => $open->filter(fn ($task) => ($task['criticality'] ?? 'normal') === 'critical' && !empty($task['due_at']) && $now->gt($task['due_at']))->count(),
                'deviations' => $tasks->where('status', 'deviation')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
            ],
            'tasks' => $tasks->values(),
        ]);
    }

    public function complete(Request $request, int $id)
    {
        $task = DB::table('haccp_tasks')->find($id);
        abort_unless($task, 404);
        $this->authorizeTask($request, $task);
        abort_unless($task->status === 'open', 422, 'Kontrolle wurde bereits bearbeitet.');

        $snapshot = json_decode($task->snapshot ?: '{}', true);
        $type = $snapshot['result_type'] ?? 'checkbox';
        $data = $request->validate([
            'numeric_value' => ['nullable', 'numeric'],
            'boolean_value' => ['nullable', 'boolean'],
            'text_value' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'deviation_description' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
        ]);

        if (in_array($type, ['temperature', 'number'], true) && !array_key_exists('numeric_value', $data)) {
            abort(422, 'Messwert erforderlich.');
        }
        if (in_array($type, ['checkbox', 'yes_no'], true) && !array_key_exists('boolean_value', $data)) {
            abort(422, 'Bestätigung erforderlich.');
        }
        if (in_array($type, ['text', 'checklist'], true) && empty(trim((string) ($data['text_value'] ?? '')))) {
            abort(422, 'Kontrollergebnis erforderlich.');
        }

        $device = $task->device_id ? DB::table('haccp_devices')->find($task->device_id) : null;
        $config = $snapshot['configuration'] ?? [];
        $min = $config['min_value'] ?? $device?->min_value;
        $max = $config['max_value'] ?? $device?->max_value;
        $within = null;

        if (isset($data['numeric_value']) && ($min !== null || $max !== null)) {
            $within = ($min === null || $data['numeric_value'] >= $min)
                && ($max === null || $data['numeric_value'] <= $max);
        }
        if (in_array($type, ['yes_no', 'checkbox'], true) && array_key_exists('boolean_value', $data)) {
            $within = (bool) $data['boolean_value'];
        }

        $criticality = $snapshot['criticality'] ?? 'normal';
        $requiresAction = ($snapshot['requires_corrective_action'] ?? true) || $criticality === 'critical';
        if ($within === false && $requiresAction && empty(trim((string) ($data['corrective_action'] ?? '')))) {
            abort(422, 'Bei dieser Abweichung ist eine Korrekturmaßnahme erforderlich.');
        }

        DB::transaction(function () use ($request, $task, $data, $within) {
            $fresh = DB::table('haccp_tasks')->where('id', $task->id)->lockForUpdate()->first();
            abort_unless($fresh?->status === 'open', 409, 'Kontrolle wurde bereits bearbeitet.');

            $resultId = DB::table('haccp_task_results')->insertGetId([
                'task_id' => $task->id,
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
                'numeric_value' => $data['numeric_value'] ?? null,
                'boolean_value' => $data['boolean_value'] ?? null,
                'text_value' => $data['text_value'] ?? null,
                'payload' => isset($data['payload']) ? json_encode($data['payload']) : null,
                'within_limits' => $within,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($within === false) {
                DB::table('haccp_deviations')->insert([
                    'task_result_id' => $resultId,
                    'description' => $data['deviation_description'] ?? 'Grenzwert bzw. Sollzustand nicht eingehalten.',
                    'corrective_action' => $data['corrective_action'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('haccp_tasks')->where('id', $task->id)->update([
                'status' => $within === false ? 'deviation' : 'completed',
                'updated_at' => now(),
            ]);
        });

        return response()->json(['ok' => true, 'within_limits' => $within]);
    }

    private function query(Request $request)
    {
        $roleIds = DB::table('haccp_role_user')
            ->where('user_id', $request->user()->id)
            ->pluck('haccp_role_id');

        $query = DB::table('haccp_tasks as t')
            ->leftJoin('haccp_devices as d', 'd.id', '=', 't.device_id')
            ->leftJoin('haccp_roles as r', 'r.id', '=', 't.haccp_role_id')
            ->select(
                't.id', 't.haccp_role_id', 't.device_id', 't.due_at', 't.status', 't.snapshot',
                'd.name as device_name', 'd.location as device_location', 'd.min_value', 'd.max_value', 'd.unit',
                'r.name as role_name'
            )
            ->whereDate('t.due_at', today());

        if ($request->user()->access_role !== 'admin') {
            $query->where(fn ($q) => $q->whereNull('t.haccp_role_id')->orWhereIn('t.haccp_role_id', $roleIds));
        }

        return $query;
    }

    private function serializeTask(object $task): array
    {
        $snapshot = json_decode($task->snapshot ?: '{}', true);
        $config = $snapshot['configuration'] ?? [];

        return [
            'id' => $task->id,
            'name' => $snapshot['name'] ?? 'HACCP-Kontrolle',
            'category' => $snapshot['category'] ?? null,
            'criticality' => $snapshot['criticality'] ?? 'normal',
            'result_type' => $snapshot['result_type'] ?? 'checkbox',
            'configuration' => $config,
            'requires_corrective_action' => (bool) ($snapshot['requires_corrective_action'] ?? true),
            'status' => $task->status,
            'due_at' => $task->due_at,
            'role_name' => $task->role_name,
            'device' => $task->device_id ? [
                'id' => $task->device_id,
                'name' => $task->device_name,
                'location' => $task->device_location,
                'unit' => $task->unit,
            ] : null,
            'min_value' => $config['min_value'] ?? $task->min_value,
            'max_value' => $config['max_value'] ?? $task->max_value,
            'unit' => $task->unit,
        ];
    }

    private function authorizeTask(Request $request, object $task): void
    {
        if ($request->user()?->access_role === 'admin' || $task->haccp_role_id === null) {
            return;
        }

        $allowed = DB::table('haccp_role_user')
            ->where('haccp_role_id', $task->haccp_role_id)
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_unless($allowed, 403);
    }
}
