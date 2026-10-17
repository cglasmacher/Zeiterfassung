<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_states', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('balance_cents')->nullable();
            $table->date('last_business_date')->nullable();
            $table->timestamps();
        });
        DB::table('cash_register_states')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('cash_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->string('status')->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->bigInteger('opening_cents');
            $table->bigInteger('float_target_cents');
            $table->json('supplement')->nullable();
            $table->json('z_report')->nullable();
            $table->json('closing')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_day_id')->constrained()->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->json('snapshot');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('cash_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_day_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('event_id')->unique();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vectron_import_states', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->unique();
            $table->string('cursor')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vectron_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_id')->unique();
            $table->string('site_id')->index();
            $table->timestamp('transaction_at')->index();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['vectron_transactions', 'vectron_import_states', 'cash_deliveries', 'cash_handovers', 'cash_days', 'cash_register_states'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
