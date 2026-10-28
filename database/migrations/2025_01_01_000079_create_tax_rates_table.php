<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's taxes (Doc 11 sections 9 to 11, Doc 04 sections 8 to 10):
 *
 * - tax_rates: VAT 15%, Exempt - each with the code imports and documents name
 *   it by (upper case, used once in the business), its percentage, its
 *   treatment (standard, zero-rated, exempt, out of scope, no tax), what it
 *   applies to, and the dates it is in effect. default_for_sales and
 *   default_for_purchases mark the business's default sales and purchase tax,
 *   one of each at most.
 * - workspaces.price_tax_mode: whether prices are entered without tax
 *   (exclusive) or with it (inclusive); tax_rounding: whether tax is rounded
 *   on each line or once on a document's total.
 * - items.sales_tax_id and purchase_tax_id: the tax a new sale or purchase of
 *   the item starts with. A rate items point at cannot be deleted (see
 *   TaxRateService); nullOnDelete is only the safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->decimal('rate', 7, 4);                    // a percentage: 15.0000
            $table->string('treatment', 20);                  // standard | zero_rated | exempt | out_of_scope | no_tax
            $table->boolean('for_sales')->default(false);
            $table->boolean('for_purchases')->default(false);
            $table->boolean('for_expenses')->default(false);
            $table->string('recoverability', 20)->nullable(); // recoverable | non_recoverable - purchase and expense tax only
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('default_for_sales')->default(false);
            $table->boolean('default_for_purchases')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
        });

        Schema::table('workspaces', function (Blueprint $t) {
            $t->string('price_tax_mode', 10)->default('exclusive')->after('tax_mode');
            $t->string('tax_rounding', 10)->default('line')->after('price_tax_mode');
        });

        Schema::table('items', function (Blueprint $t) {
            $t->foreignId('sales_tax_id')->nullable()->after('sale_price')->constrained('tax_rates')->nullOnDelete();
            $t->foreignId('purchase_tax_id')->nullable()->after('purchase_price')->constrained('tax_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sales_tax_id');
            $t->dropConstrainedForeignId('purchase_tax_id');
        });

        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropColumn(['price_tax_mode', 'tax_rounding']);
        });

        Schema::dropIfExists('tax_rates');
    }
};
