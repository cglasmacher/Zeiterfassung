<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haccp_cleaning_plans', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('active');
            $table->foreignId('supersedes_cleaning_plan_id')->nullable()->after('version')->constrained('haccp_cleaning_plans')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('supersedes_cleaning_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_cleaning_plans', function (Blueprint $table) {
            $table->dropForeign(['supersedes_cleaning_plan_id']);
            $table->dropColumn(['version','supersedes_cleaning_plan_id','locked_at']);
        });
    }
};
