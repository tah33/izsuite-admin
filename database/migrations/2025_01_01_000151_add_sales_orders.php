<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase B1: sales orders, a kind of sales document the full Sales app
 * makes (section 16.9). An order keeps when it was confirmed and by whom, and
 * beside its own status where its delivery and invoicing stand. Each line of
 * any sales document can count what was delivered, invoiced, returned and
 * credited of it; an amended order's earlier version keeps the reason it was
 * changed. The menu's Sales Orders item, there since Doc 13, now needs the
 * orders' View.
 */
return new class extends Migration
{
    private const QUANTITIES = ['delivered_quantity', 'invoiced_quantity', 'returned_quantity', 'credited_quantity'];

    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('issued_at');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->string('delivery_status', 20)->nullable()->after('payment_status');   // an order's: not_required | not_delivered | ...
            $table->string('invoice_status', 20)->nullable()->after('delivery_status');   // an order's: not_invoiced | partially_invoiced | invoiced
        });

        Schema::table('sales_document_lines', function (Blueprint $table) {
            foreach (array_reverse(self::QUANTITIES) as $column) {
                $table->decimal($column, 15, 3)->default(0)->after('quantity');
            }
        });

        Schema::table('sales_document_revisions', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->after('snapshot');
        });

        DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/orders')->update(['permission_key' => 'sales.orders.view']);
        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'sales')->where('route', '/sales/orders')->update(['permission_key' => null]);
        CatalogVersion::bump();

        Schema::table('sales_document_revisions', fn (Blueprint $table) => $table->dropColumn('reason'));
        Schema::table('sales_document_lines', fn (Blueprint $table) => $table->dropColumn(self::QUANTITIES));

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['confirmed_at', 'delivery_status', 'invoice_status']);
        });
    }
};
