<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_document_archives', function (Blueprint $table) {
            $table->id();
            $table->string('document_key')->unique();
            $table->string('kind', 20);
            $table->unsignedBigInteger('source_id');
            $table->unique(['kind', 'source_id']);
            $table->string('remote_path', 500);
            $table->timestamp('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_document_archives');
    }
};
