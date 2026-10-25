<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->unsignedInteger('width')->default(1200);
            $table->unsignedInteger('height')->default(800);
            $table->unsignedInteger('revision')->default(1);
            $table->json('elements')->nullable();
            $table->timestamps();
        });
        Schema::create('reservation_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('reservation_rooms')->restrictOnDelete();
            $table->string('name', 40);
            $table->unsignedSmallInteger('capacity');
            $table->string('shape', 16);
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_tables');
        Schema::dropIfExists('reservation_rooms');
    }
};
