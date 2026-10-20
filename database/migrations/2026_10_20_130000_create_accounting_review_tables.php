<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('creditor_account', 20)->nullable()->index();
            $table->json('aliases')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 20)->unique();
            $table->string('name');
            $table->string('kind', 32)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_export_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('draft')->index();
            $table->string('filename')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('n8n')->index();
            $table->string('source_reference')->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained('accounting_suppliers')->nullOnDelete();
            $table->string('supplier_name_raw')->nullable();
            $table->string('invoice_number')->nullable()->index();
            $table->date('invoice_date')->nullable();
            $table->date('service_date_from')->nullable();
            $table->date('service_date_to')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->bigInteger('net_cents')->nullable();
            $table->bigInteger('tax_cents')->nullable();
            $table->bigInteger('gross_cents')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status', 32)->default('received')->index();
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('export_batch_id')->nullable()->constrained('accounting_export_batches')->nullOnDelete();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'invoice_number']);
        });

        Schema::create('accounting_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('accounting_documents')->cascadeOnDelete();
            $table->unsignedInteger('position_no')->nullable();
            $table->string('article_number')->nullable()->index();
            $table->text('description');
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->bigInteger('net_cents')->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->bigInteger('tax_cents')->nullable();
            $table->bigInteger('gross_cents')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('account_number_snapshot', 20)->nullable();
            $table->string('classification_source', 24)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status', 24)->default('proposed')->index();
            $table->string('rule_key')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('accounting_suppliers')->cascadeOnDelete();
            $table->string('match_type', 32);
            $table->string('match_value');
            $table->foreignId('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->integer('priority')->default(100);
            $table->boolean('learned_from_manual')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'match_type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_rules');
        Schema::dropIfExists('accounting_document_lines');
        Schema::dropIfExists('accounting_documents');
        Schema::dropIfExists('accounting_export_batches');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('accounting_suppliers');
    }
};
