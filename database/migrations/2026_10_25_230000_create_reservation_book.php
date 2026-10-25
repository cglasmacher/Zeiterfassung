<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->json('configuration');
            $table->unsignedInteger('revision')->default(1);
        });
        DB::table('reservation_settings')->insert(['id' => 1, 'configuration' => json_encode([
            'weekdays' => [0, 1, 3, 4, 5, 6], 'opens_at' => '10:00', 'closes_at' => '20:00',
            'slot_minutes' => 30, 'default_duration' => 90,
            'durations' => [['from' => 2, 'minutes' => 105], ['from' => 4, 'minutes' => 135], ['from' => 6, 'minutes' => 150]],
            'require_name' => true, 'require_phone' => true,
        ]), 'revision' => 1]);
        Schema::create('reservation_combinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('reservation_rooms')->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity');
            $table->json('table_ids');
            $table->timestamps();
        });
        Schema::create('reservation_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('source', 20);
            $table->string('status', 20);
            $table->string('guest_name', 120)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 200)->nullable();
            $table->unsignedSmallInteger('party_size');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
            $table->index('status');
        });
        Schema::create('reservation_booking_table', function (Blueprint $table) {
            $table->foreignUuid('booking_id')->constrained('reservation_bookings')->cascadeOnDelete();
            $table->foreignUuid('table_id')->constrained('reservation_tables')->restrictOnDelete();
            $table->primary(['booking_id', 'table_id']);
        });
        Schema::create('reservation_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reason', 200);
            $table->foreignUuid('room_id')->nullable()->constrained('reservation_rooms')->restrictOnDelete();
            $table->json('table_ids')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });
        Schema::create('reservation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('booking_id')->constrained('reservation_bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('details');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['reservation_events', 'reservation_blocks', 'reservation_booking_table', 'reservation_bookings', 'reservation_combinations', 'reservation_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
