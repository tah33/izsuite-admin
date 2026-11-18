<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase B2: invoicing from sales orders (sections 16.11 and 16.12). A
 * business invoices its orders' ordered quantities, or their delivered ones
 * (Settings → Sales); an item may say otherwise, and null follows the
 * business. An invoice that bills beyond what its order has left keeps the
 * reason it was allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_settings', function (Blueprint $table) {
            $table->string('invoice_policy', 20)->default('ordered');     // ordered | delivered
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('invoice_policy', 20)->nullable();              // null: the business's
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('over_invoice_reason', 500)->nullable()->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn('over_invoice_reason'));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('invoice_policy'));
        Schema::table('sales_settings', fn (Blueprint $table) => $table->dropColumn('invoice_policy'));
    }
};
