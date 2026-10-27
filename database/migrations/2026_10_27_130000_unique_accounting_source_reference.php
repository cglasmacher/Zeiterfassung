<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep every existing original and correction. Never silently merge duplicates.
        $duplicate = DB::table('accounting_documents')
            ->select('source', 'source_reference')
            ->whereNotNull('source_reference')
            ->groupBy('source', 'source_reference')
            ->havingRaw('COUNT(*) > 1')->first();
        if ($duplicate) {
            throw new RuntimeException('Doppelte Buchhaltungs-Quellreferenzen vorhanden. Vor der Migration manuell prüfen; es wurden keine Belege gelöscht.');
        }

        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->unique(['source', 'source_reference'], 'accounting_documents_source_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->dropUnique('accounting_documents_source_reference_unique');
        });
    }
};
