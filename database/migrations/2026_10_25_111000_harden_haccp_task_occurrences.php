<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->unique(['template_id','due_at'],'haccp_tasks_template_due_unique');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_tasks', function (Blueprint $table) {
            $table->dropUnique('haccp_tasks_template_due_unique');
        });
    }
};
