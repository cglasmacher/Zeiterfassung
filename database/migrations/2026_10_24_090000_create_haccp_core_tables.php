<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('haccp_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('haccp_role_user', function (Blueprint $table) {
            $table->foreignId('haccp_role_id')->constrained('haccp_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['haccp_role_id', 'user_id']);
        });

        Schema::create('haccp_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('location')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('commissioned_at')->nullable();
            $table->decimal('min_value', 8, 2)->nullable();
            $table->decimal('max_value', 8, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('qr_token', 64)->unique()->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('haccp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->foreignId('haccp_role_id')->nullable()->constrained('haccp_roles')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('haccp_devices')->nullOnDelete();
            $table->string('result_type')->default('checkbox');
            $table->json('configuration')->nullable();
            $table->string('frequency')->default('daily');
            $table->json('schedule')->nullable();
            $table->boolean('requires_corrective_action')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('haccp_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('haccp_templates')->restrictOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('haccp_devices')->nullOnDelete();
            $table->foreignId('haccp_role_id')->nullable()->constrained('haccp_roles')->nullOnDelete();
            $table->string('title');
            $table->timestamp('due_from')->nullable();
            $table->timestamp('due_at');
            $table->string('status')->default('open');
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });

        Schema::create('haccp_task_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('haccp_tasks')->restrictOnDelete();
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->decimal('numeric_value', 10, 2)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->text('text_value')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('within_limits')->nullable();
            $table->timestamps();
        });

        Schema::create('haccp_deviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_result_id')->constrained('haccp_task_results')->restrictOnDelete();
            $table->text('description');
            $table->text('corrective_action')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('haccp_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('haccp_devices')->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency');
            $table->unsignedInteger('interval_value')->default(1);
            $table->date('last_completed_at')->nullable();
            $table->date('next_due_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('haccp_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained('haccp_maintenance_plans')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('completed_at');
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('haccp_monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->string('document_path')->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haccp_monthly_closings');
        Schema::dropIfExists('haccp_maintenance_records');
        Schema::dropIfExists('haccp_maintenance_plans');
        Schema::dropIfExists('haccp_deviations');
        Schema::dropIfExists('haccp_task_results');
        Schema::dropIfExists('haccp_tasks');
        Schema::dropIfExists('haccp_templates');
        Schema::dropIfExists('haccp_devices');
        Schema::dropIfExists('haccp_role_user');
        Schema::dropIfExists('haccp_roles');
    }
};
