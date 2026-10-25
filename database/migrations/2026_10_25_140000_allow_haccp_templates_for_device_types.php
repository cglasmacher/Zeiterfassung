<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haccp_templates', function (Blueprint $table) {
            $table->foreignId('device_type_id')->nullable()->after('device_id')->constrained('haccp_device_types')->nullOnDelete();
        });

        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->string('occurrence_key', 64)->nullable()->after('template_id');
        });

        DB::table('haccp_tasks')->orderBy('id')->chunkById(200, function ($tasks) {
            foreach ($tasks as $task) {
                DB::table('haccp_tasks')->where('id', $task->id)->update([
                    'occurrence_key' => hash('sha256', $task->template_id.'|'.($task->device_id ?: 'general').'|'.$task->due_at),
                ]);
            }
        });

        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->dropUnique('haccp_tasks_template_due_unique');
            $table->unique('occurrence_key', 'haccp_tasks_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->dropUnique('haccp_tasks_occurrence_unique');
            $table->dropColumn('occurrence_key');
            $table->unique(['template_id', 'due_at'], 'haccp_tasks_template_due_unique');
        });

        Schema::table('haccp_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_type_id');
        });
    }
};
