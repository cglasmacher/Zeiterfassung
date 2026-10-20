<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_voucher_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_day_id')->constrained('cash_days');
            $table->foreignId('handover_id')->nullable()->constrained('cash_handovers');
            $table->string('source_key', 100)->unique();
            $table->string('label');
            $table->unsignedBigInteger('amount_cents');
            $table->uuid('registration_request_id')->nullable()->unique();
            $table->char('registration_fingerprint', 64)->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('cash_voucher_batches');
            $table->string('number', 32);
            $table->string('number_key', 32)->unique();
            $table->date('sold_on');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('balance_cents');
            $table->string('payment_method', 10);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('cash_voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('voucher_id')->constrained('cash_vouchers');
            $table->foreignId('cash_day_id')->constrained('cash_days');
            $table->foreignId('handover_id')->nullable()->constrained('cash_handovers');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['cash_day_id', 'handover_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_voucher_redemptions');
        Schema::dropIfExists('cash_vouchers');
        Schema::dropIfExists('cash_voucher_batches');
    }
};
