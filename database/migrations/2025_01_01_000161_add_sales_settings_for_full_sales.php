<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase H1 (section 16.23): the rest of Settings > Sales on the full
 * Sales app - what a new sale starts as, a default price list tried after
 * the others, the percentage a deposit invoice starts with, whether a
 * partial delivery makes a backorder (ask, always or never), the shipping
 * terms new documents start with, and whether whoever makes a document is
 * its salesperson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_settings', function (Blueprint $table) {
            $table->string('new_sales_start_as', 20)->default('invoice')->after('link_comments');
            $table->foreignId('default_price_list_id')->nullable()->after('new_sales_start_as')->constrained('price_lists')->nullOnDelete();
            $table->decimal('deposit_percent', 5, 2)->nullable()->after('default_price_list_id');
            $table->string('backorders', 10)->default('ask')->after('deposit_percent');
            $table->string('default_shipping_terms', 100)->nullable()->after('backorders');
            $table->boolean('salesperson_is_creator')->default(false)->after('default_shipping_terms');
        });
    }

    public function down(): void
    {
        Schema::table('sales_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_price_list_id');
            $table->dropColumn(['new_sales_start_as', 'deposit_percent', 'backorders', 'default_shipping_terms', 'salesperson_is_creator']);
        });
    }
};
