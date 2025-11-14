<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->decimal('total_hours', 6, 2)->nullable()->after('break_minutes');
            $table->decimal('gross_wage', 8, 2)->nullable()->after('total_hours');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['total_hours', 'gross_wage']);
        });
    }
};