<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('break_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // z. B. "Standardregel DE"
            $table->decimal('min_hours', 4, 2); // ab wie vielen Stunden gilt die Regel
            $table->decimal('break_minutes', 5, 2); // wie viele Minuten Pause
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_rules');
    }
};
