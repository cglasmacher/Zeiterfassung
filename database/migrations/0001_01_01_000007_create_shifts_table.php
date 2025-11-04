<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('shift_date');
            $table->time('start_time')->nullable(); // überschreibt ggf. default_start aus shift_types
            $table->time('end_time')->nullable();
            $table->decimal('planned_hours', 5, 2)->nullable();
            $table->enum('status', ['planned', 'worked', 'absent'])->default('planned');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};


