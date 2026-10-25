<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('haccp_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        DB::table('haccp_settings')->insert([
            ['key'=>'food_sampling','value'=>json_encode(['samples_per_month'=>4,'weekdays'=>[1,2,3,4,5,6],'default_min_temperature'=>null,'default_max_temperature'=>null]),'created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('haccp_settings');
    }
};
