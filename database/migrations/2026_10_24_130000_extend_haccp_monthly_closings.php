<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haccp_monthly_closings', function (Blueprint $table) {
            $table->json('snapshot')->nullable()->after('closed_at');
            $table->json('statistics')->nullable()->after('snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_monthly_closings', function (Blueprint $table) {
            $table->dropColumn(['snapshot', 'statistics']);
        });
    }
};
