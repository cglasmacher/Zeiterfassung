<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('gross_wage');
            $table->decimal('override_hourly_rate', 8, 2)->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['admin_note', 'override_hourly_rate']);
        });
    }
};