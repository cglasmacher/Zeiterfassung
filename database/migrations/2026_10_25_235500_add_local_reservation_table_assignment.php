<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->json('resmio_local_table_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropColumn('resmio_local_table_ids');
        });
    }
};
