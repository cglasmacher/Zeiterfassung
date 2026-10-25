<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('haccp_tasks')
            ->select('template_id', 'due_at', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('template_id', 'due_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $tasks = DB::table('haccp_tasks')
                ->where('template_id', $duplicate->template_id)
                ->where('due_at', $duplicate->due_at)
                ->orderBy('id')
                ->get();

            $taskIds = $tasks->pluck('id');
            $resultTaskIds = DB::table('haccp_task_results')
                ->whereIn('task_id', $taskIds)
                ->pluck('task_id')
                ->unique()
                ->values();

            if ($resultTaskIds->count() > 1) {
                throw new \RuntimeException(
                    "HACCP-Dubletten für Template {$duplicate->template_id} / {$duplicate->due_at} enthalten mehrere dokumentierte Tasks. Automatische Bereinigung abgebrochen."
                );
            }

            // A documented task always wins. Otherwise retain the oldest task.
            $keepId = $resultTaskIds->first() ?: $taskIds->first();
            $deleteIds = $taskIds->reject(fn ($id) => (int) $id === (int) $keepId)->values();

            if ($deleteIds->isNotEmpty()) {
                DB::table('haccp_tasks')->whereIn('id', $deleteIds)->delete();
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
