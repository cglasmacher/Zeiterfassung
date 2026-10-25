<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->string('resmio_facility', 100)->nullable();
            $table->string('resmio_id', 100)->nullable();
            $table->string('resmio_status', 40)->nullable();
            $table->string('resmio_local_status', 20)->nullable();
            $table->dateTime('resmio_updated_at')->nullable();
            $table->dateTime('resmio_synced_at')->nullable();
            $table->text('resmio_notes')->nullable();
            $table->text('guest_comment')->nullable();
            $table->text('customer_notes')->nullable();
            $table->json('resmio_tags')->nullable();
            $table->json('customer_tags')->nullable();
            $table->json('resmio_tables')->nullable();
            $table->json('resmio_data')->nullable();
            $table->json('resmio_issues')->nullable();
            $table->boolean('resmio_unassigned')->default(false);
            $table->boolean('resmio_missing')->default(false);
            $table->unique(['resmio_facility', 'resmio_id']);
        });
        Schema::create('reservation_resmio_resources', function (Blueprint $table) {
            $table->id();
            $table->string('facility', 100);
            $table->string('external_id', 100);
            $table->string('name');
            $table->foreignUuid('table_id')->nullable()->constrained('reservation_tables')->restrictOnDelete();
            $table->string('mapping_mode', 10)->default('auto');
            $table->unsignedInteger('revision')->default(1);
            $table->unique(['facility', 'external_id']);
        });
        Schema::create('reservation_resmio_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('facility', 100);
            $table->string('external_id', 100);
            $table->dateTime('deleted_at');
            $table->unique(['facility', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_resmio_deletions');
        Schema::dropIfExists('reservation_resmio_resources');
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropUnique(['resmio_facility', 'resmio_id']);
            $table->dropColumn(['resmio_facility', 'resmio_id', 'resmio_status', 'resmio_local_status', 'resmio_updated_at', 'resmio_synced_at', 'resmio_notes', 'guest_comment', 'customer_notes', 'resmio_tags', 'customer_tags', 'resmio_tables', 'resmio_data', 'resmio_issues', 'resmio_unassigned', 'resmio_missing']);
        });
    }
};
