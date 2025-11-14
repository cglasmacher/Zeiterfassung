<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('department_shift_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Verhindere Duplikate
            $table->unique(['department_id', 'shift_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_shift_type');
    }
};