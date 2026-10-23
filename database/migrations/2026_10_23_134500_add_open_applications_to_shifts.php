<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('is_open_for_applications')->default(false)->after('status');
        });

        // Rebuild the employee foreign key so a shift can exist before it is assigned.
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
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
            $table->dropForeign(['employee_id']);
            $table->dropColumn('is_open_for_applications');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable(false)->change();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }
};
