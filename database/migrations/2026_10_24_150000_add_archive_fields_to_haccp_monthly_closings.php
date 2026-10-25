<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haccp_monthly_closings', function (Blueprint $table) {
            $table->string('document_number')->nullable()->after('closed_at');
            $table->string('pdf_path')->nullable()->after('document_number');
            $table->string('pdf_sha256', 64)->nullable()->after('pdf_path');
            $table->unsignedBigInteger('pdf_size')->nullable()->after('pdf_sha256');
            $table->timestamp('archived_at')->nullable()->after('pdf_size');
        });
    }

    public function down(): void
    {
        Schema::table('haccp_monthly_closings', function (Blueprint $table) {
            $table->dropColumn(['document_number','pdf_path','pdf_sha256','pdf_size','archived_at']);
        });
    }
};
