<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase F1: the CRM opportunity a sales document was made from - a
 * quotation, proforma invoice, sales order or invoice made from it, and
 * what is converted from one of those. One made before this has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->after('source_id')->constrained('crm_opportunities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opportunity_id');
        });
    }
};
