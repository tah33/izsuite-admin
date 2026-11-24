<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase F2 - confirming an online order (section 19.20):
 *
 * - ecommerce_orders.sales_document_id: the invoice its confirmation issued;
 * - ecommerce_orders.confirmed_at and confirmed_by: when it was confirmed,
 *   and by whom.
 *
 * Confirm Orders is a permission of the code's (App\Support\Permissions),
 * so no row is added for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->foreignId('sales_document_id')->nullable()->after('contact_created')->constrained('sales_documents')->nullOnDelete();   // its invoice
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
            $table->dropConstrainedForeignId('sales_document_id');
        });
    }
};
