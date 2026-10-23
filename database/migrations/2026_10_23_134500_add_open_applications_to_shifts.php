<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // employee_id is already nullable via 0001_01_01_000023.
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('is_open_for_applications')->default(false)->after('status');
        });

        Schema::create('shift_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['shift_id', 'employee_id']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_applications');

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('is_open_for_applications');
        });
    }
};
