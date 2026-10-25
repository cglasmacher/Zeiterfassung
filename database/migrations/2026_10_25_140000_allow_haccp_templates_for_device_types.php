<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('haccp_templates', 'device_type_id')) {
            Schema::table('haccp_templates', function (Blueprint $table) {
                $table->foreignId('device_type_id')->nullable()->after('device_id')->constrained('haccp_device_types')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('haccp_tasks', 'occurrence_key')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->string('occurrence_key', 64)->nullable()->after('template_id');
            });
        }

        DB::table('haccp_tasks')->orderBy('id')->chunkById(200, function ($tasks) {
            foreach ($tasks as $task) {
                DB::table('haccp_tasks')->where('id', $task->id)->update([
                    'occurrence_key' => hash('sha256', $task->template_id.'|'.($task->device_id ?: 'general').'|'.$task->due_at),
                ]);
            }
        });

        // MySQL may use the old composite unique index as the supporting index for
        // the template_id foreign key. Add a dedicated FK index before removing it.
        if (!$this->indexExists('haccp_tasks', 'haccp_tasks_template_id_index')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->index('template_id', 'haccp_tasks_template_id_index');
            });
        }

        if ($this->indexExists('haccp_tasks', 'haccp_tasks_template_due_unique')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->dropUnique('haccp_tasks_template_due_unique');
            });
        }

        if (!$this->indexExists('haccp_tasks', 'haccp_tasks_occurrence_unique')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->unique('occurrence_key', 'haccp_tasks_occurrence_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('haccp_tasks', 'haccp_tasks_occurrence_unique')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->dropUnique('haccp_tasks_occurrence_unique');
            });
        }

        if (Schema::hasColumn('haccp_tasks', 'occurrence_key')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->dropColumn('occurrence_key');
            });
        }

        if (!$this->indexExists('haccp_tasks', 'haccp_tasks_template_due_unique')) {
            Schema::table('haccp_tasks', function (Blueprint $table) {
                $table->unique(['template_id', 'due_at'], 'haccp_tasks_template_due_unique');
            });
        }

        if (Schema::hasColumn('haccp_templates', 'device_type_id')) {
            Schema::table('haccp_templates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('device_type_id');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
