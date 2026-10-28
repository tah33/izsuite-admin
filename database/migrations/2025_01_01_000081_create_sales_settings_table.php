<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings > Sales (Doc 11 section 15): what a new quotation, proforma invoice
 * or invoice starts with. One row per business, made when its settings are
 * first saved - until then, the defaults below apply.
 *
 * - payment_terms: days until an invoice is due, for a customer without terms
 *   of their own (Doc 11 section 14). 0 is Due on Receipt.
 * - quotation_validity, proforma_validity: days until one expires.
 * - line_discounts, document_discounts: whether documents offer them.
 * - notes, terms: the notes and the terms and conditions a new document has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('payment_terms')->default(0);
            $table->unsignedSmallInteger('quotation_validity')->default(30);
            $table->unsignedSmallInteger('proforma_validity')->default(30);
            $table->boolean('line_discounts')->default(true);
            $table->boolean('document_discounts')->default(true);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_settings');
    }
};
