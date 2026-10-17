<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_z_reports', function (Blueprint $table) {
            $table->id();
            $table->char('sha256', 64)->unique();
            $table->string('path');
            $table->string('original_name');
            $table->string('report_number', 50)->nullable();
            $table->string('register_name', 100)->nullable();
            $table->dateTime('reported_at')->nullable();
            $table->date('business_date')->nullable()->index();
            $table->string('assignment_source', 20)->default('pending');
            $table->string('message_id', 500)->nullable();
            $table->json('assignment_history')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_z_reports');
    }
};
