<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_time_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('total_hours', 6, 2)->default(0);     // Arbeitszeit in Stunden
            $table->decimal('total_break_minutes', 5, 2)->default(0);
            $table->integer('segments')->default(0);              // Anzahl Teildienste
            $table->decimal('overtime_hours', 6, 2)->default(0);  // optional für spätere Berechnung
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_time_summaries');
    }
};
