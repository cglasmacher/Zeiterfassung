<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('haccp_device_types', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->string('unit', 20)->nullable();
            $table->decimal('default_min_value', 8, 2)->nullable(); $table->decimal('default_max_value', 8, 2)->nullable();
            $table->string('default_frequency')->nullable(); $table->unsignedInteger('default_interval_value')->nullable();
            $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::table('haccp_devices', function (Blueprint $table) {
            $table->foreignId('device_type_id')->nullable()->after('name')->constrained('haccp_device_types')->restrictOnDelete();
        });
        Schema::table('haccp_templates', function (Blueprint $table) {
            $table->string('criticality')->default('normal')->after('category');
            $table->unsignedInteger('version')->default(1); $table->foreignId('supersedes_template_id')->nullable()->constrained('haccp_templates')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
        });
        Schema::table('haccp_roles', function (Blueprint $table) {
            $table->dropUnique('haccp_roles_name_unique');
            $table->unsignedInteger('version')->default(1); $table->foreignId('supersedes_role_id')->nullable()->constrained('haccp_roles')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->unique(['name','version'],'haccp_roles_name_version_unique');
        });
        Schema::create('haccp_cleaning_plans', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('location')->nullable(); $table->text('activity');
            $table->string('cleaning_agent')->nullable(); $table->string('disinfectant')->nullable();
            $table->foreignId('haccp_role_id')->nullable()->constrained('haccp_roles')->nullOnDelete();
            $table->string('frequency'); $table->unsignedInteger('interval_value')->default(1); $table->json('schedule')->nullable();
            $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('haccp_cleaning_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('cleaning_plan_id')->constrained('haccp_cleaning_plans')->restrictOnDelete();
            $table->date('due_date'); $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
            $table->unique(['cleaning_plan_id','due_date']);
        });
        Schema::create('haccp_goods_receipts', function (Blueprint $table) {
            $table->id(); $table->string('supplier')->nullable(); $table->string('item'); $table->boolean('visual_ok');
            $table->boolean('olfactory_ok'); $table->boolean('supplier_docs_complete'); $table->decimal('temperature',8,2)->nullable();
            $table->text('notes')->nullable(); $table->foreignId('completed_by')->constrained('users')->restrictOnDelete(); $table->timestamp('completed_at'); $table->timestamps();
        });
        Schema::create('haccp_food_samples', function (Blueprint $table) {
            $table->id(); $table->date('scheduled_for')->index(); $table->string('dish')->nullable(); $table->decimal('core_temperature',8,2)->nullable();
            $table->boolean('sensory_ok')->nullable(); $table->text('notes')->nullable(); $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable(); $table->string('status')->default('open'); $table->timestamps();
        });
        Schema::create('haccp_retention_samples', function (Blueprint $table) {
            $table->id(); $table->string('product'); $table->string('batch')->nullable(); $table->timestamp('produced_at')->nullable();
            $table->timestamp('sampled_at'); $table->string('label')->nullable(); $table->string('storage_location')->nullable(); $table->date('dispose_on')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete(); $table->text('notes')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haccp_retention_samples'); Schema::dropIfExists('haccp_food_samples'); Schema::dropIfExists('haccp_goods_receipts');
        Schema::dropIfExists('haccp_cleaning_records'); Schema::dropIfExists('haccp_cleaning_plans');
        Schema::table('haccp_templates', function(Blueprint $t){$t->dropConstrainedForeignId('supersedes_template_id');$t->dropColumn(['criticality','version','locked_at']);});
        Schema::table('haccp_roles', function(Blueprint $t){$t->dropUnique('haccp_roles_name_version_unique');$t->dropConstrainedForeignId('supersedes_role_id');$t->dropColumn(['version','locked_at']);$t->unique('name','haccp_roles_name_unique');});
        Schema::table('haccp_devices', fn(Blueprint $t) => $t->dropConstrainedForeignId('device_type_id'));
        Schema::dropIfExists('haccp_device_types');
    }
};
