<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
        });
        DB::table('reservation_bookings')->where('status', 'confirmed')->where('source', '!=', 'resmio')->update(['confirmed_at' => DB::raw('created_at')]);
        // Keep a dedicated FK index before replacing the event's composite unique index (MySQL).
        Schema::table('reservation_mail_outbox', fn (Blueprint $table) => $table->index('booking_id', 'reservation_mail_booking_lookup'));
        Schema::table('reservation_mail_outbox', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'booking_version']);
            $table->unique(['booking_id', 'booking_version', 'type'], 'reservation_mail_event_unique');
            $table->string('reminder_key', 64)->nullable()->unique();
        });
        Schema::create('reservation_mail_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->boolean('reminders_enabled')->default(true);
            $table->unsignedSmallInteger('reminder_hours')->default(24);
            $table->unsignedInteger('revision')->default(1);
        });
        DB::table('reservation_mail_settings')->insert(['id' => 1, 'reminders_enabled' => true, 'reminder_hours' => 24, 'revision' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_mail_settings');
        // Remove reminder rows before restoring the previous event uniqueness.
        DB::table('reservation_mail_outbox')->where('type', 'reminder')->delete();
        Schema::table('reservation_mail_outbox', function (Blueprint $table) {
            $table->dropUnique('reservation_mail_event_unique');
            $table->dropUnique(['reminder_key']);
            $table->dropColumn('reminder_key');
            $table->unique(['booking_id', 'booking_version']);
        });
        Schema::table('reservation_mail_outbox', fn (Blueprint $table) => $table->dropIndex('reservation_mail_booking_lookup'));
        Schema::table('reservation_bookings', fn (Blueprint $table) => $table->dropColumn('confirmed_at'));
    }
};
