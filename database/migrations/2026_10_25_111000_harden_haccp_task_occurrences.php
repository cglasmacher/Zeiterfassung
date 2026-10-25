<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration {
    public function up(): void
    {
        // Older generator runs could create the same occurrence more than once.
        // Clean up only duplicate tasks that have no documented result. Never
        // delete a task that already forms part of the HACCP audit trail.
        $duplicates = DB::table('haccp_tasks')
            ->select('template_id', 'due_at', DB::raw('COUNT(*) as occurrences'))
            ->groupBy('template_id', 'due_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $tasks = DB::table('haccp_tasks as t')
                ->leftJoin('haccp_task_results as r', 'r.task_id', '=', 't.id')
                ->where('t.template_id', $duplicate->template_id)
                ->where('t.due_at', $duplicate->due_at)
                ->select('t.id', 't.status', DB::raw('COUNT(r.id) as result_count'))
                ->groupBy('t.id', 't.status')
                ->orderByRaw("CASE WHEN COUNT(r.id) > 0 THEN 0 WHEN t.status <> 'open' THEN 1 ELSE 2 END")
                ->orderBy('t.id')
                ->get();

            $documented = $tasks->filter(fn ($task) => (int) $task->result_count > 0);

            if ($documented->count() > 1) {
                throw new RuntimeException(
                    "HACCP-Dubletten für Template {$duplicate->template_id} / {$duplicate->due_at} enthalten mehrere dokumentierte Kontrollen. Bitte vor der Migration manuell prüfen."
                );
            }

            $keeper = $documented->first() ?? $tasks->first();
            $removableIds = $tasks
                ->where('id', '!=', $keeper->id)
                ->filter(fn ($task) => (int) $task->result_count === 0)
                ->pluck('id');

            if ($removableIds->isNotEmpty()) {
                DB::table('haccp_tasks')->whereIn('id', $removableIds)->delete();
            }

            $remaining = DB::table('haccp_tasks')
                ->where('template_id', $duplicate->template_id)
                ->where('due_at', $duplicate->due_at)
                ->count();

            if ($remaining > 1) {
                throw new RuntimeException(
                    "HACCP-Dubletten für Template {$duplicate->template_id} / {$duplicate->due_at} konnten nicht verlustfrei bereinigt werden."
                );
            }
        }

        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->unique(['template_id', 'due_at'], 'haccp_tasks_template_due_unique');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->dropUnique('haccp_tasks_template_due_unique');
        });
    }
};
