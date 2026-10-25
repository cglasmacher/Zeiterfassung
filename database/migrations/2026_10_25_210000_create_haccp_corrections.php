<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('haccp_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('monthly_closing_id')->nullable()->constrained('haccp_monthly_closings')->nullOnDelete();
            $table->text('reason');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->json('field_changes');
            $table->timestamp('corrected_at');
            $table->timestamps();
            $table->index(['source_type','source_id']);
            $table->index('corrected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haccp_corrections');
    }
};
