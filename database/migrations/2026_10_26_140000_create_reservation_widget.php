<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_widget_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->json('configuration');
            $table->unsignedInteger('revision')->default(1);
        });
        DB::table('reservation_widget_settings')->insert(['id' => 1, 'revision' => 1, 'configuration' => json_encode([
            'enabled' => false, 'waitlist_enabled' => true, 'room_selection' => true,
            'min_party' => 1, 'max_party' => 8, 'lead_minutes' => 30, 'max_days' => 365,
            'confirmation_mode' => 'automatic', 'max_auto_party' => 8, 'privacy_url' => '',
        ])]);
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->uuid('public_request_key')->nullable()->unique();
            $table->string('public_request_hash', 64)->nullable();
            $table->string('public_reference', 16)->nullable()->unique();
            $table->json('public_meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropUnique(['public_request_key']);
            $table->dropUnique(['public_reference']);
            $table->dropColumn(['public_request_key', 'public_request_hash', 'public_reference', 'public_meta']);
        });
        Schema::dropIfExists('reservation_widget_settings');
    }
};
