<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haccp_deviations', function (Blueprint $table) {
            $table->foreignId('task_result_id')->nullable()->change();
            $table->string('source_type',40)->nullable()->after('task_result_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('title')->nullable()->after('source_id');
            $table->string('criticality',20)->default('normal')->after('title');
            $table->foreignId('detected_by')->nullable()->after('criticality')->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at')->nullable()->after('detected_by');
            $table->index(['source_type','source_id']);
        });
        Schema::table('haccp_goods_receipts', function (Blueprint $table) {
            $table->decimal('min_temperature',8,2)->nullable()->after('temperature');
            $table->decimal('max_temperature',8,2)->nullable()->after('min_temperature');
        });
        Schema::table('haccp_food_samples', function (Blueprint $table) {
            $table->decimal('min_core_temperature',8,2)->nullable()->after('core_temperature');
            $table->decimal('max_core_temperature',8,2)->nullable()->after('min_core_temperature');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_food_samples',fn(Blueprint $table)=>$table->dropColumn(['min_core_temperature','max_core_temperature']));
        Schema::table('haccp_goods_receipts',fn(Blueprint $table)=>$table->dropColumn(['min_temperature','max_temperature']));
        Schema::table('haccp_deviations',function(Blueprint $table){$table->dropIndex(['source_type','source_id']);$table->dropConstrainedForeignId('detected_by');$table->dropColumn(['source_type','source_id','title','criticality','detected_at']);});
    }
};
