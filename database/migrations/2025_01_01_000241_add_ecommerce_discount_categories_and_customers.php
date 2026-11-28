<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H2b: who and what a discount is for (section 19.31).
 *
 * - ecommerce_discounts.applies_to may be categories as well (a string
 *   already): ecommerce_discount_categories holds the Items' categories a
 *   discount for chosen categories is for;
 * - ecommerce_discounts.customers: everyone, signed_in (anyone signed in to
 *   their account on the store) or contacts - ecommerce_discount_contacts
 *   holding the contacts chosen, each fitting while signed in to their
 *   account, its e-mail verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_discounts', function (Blueprint $table) {
            $table->string('customers', 20)->default('everyone')->after('applies_to');    // everyone | signed_in | contacts
        });

        Schema::create('ecommerce_discount_categories', function (Blueprint $table) {
            $table->foreignId('ecommerce_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained()->cascadeOnDelete();

            $table->primary(['ecommerce_discount_id', 'item_category_id']);
        });

        Schema::create('ecommerce_discount_contacts', function (Blueprint $table) {
            $table->foreignId('ecommerce_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            $table->primary(['ecommerce_discount_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        // The code before knows neither: such a discount would be taken by everyone, on every product - deactivated
        // instead, one for categories left for products (none chosen).
        DB::table('ecommerce_discounts')->where('applies_to', 'categories')->orWhere('customers', '!=', 'everyone')->update(['is_active' => false]);
        DB::table('ecommerce_discounts')->where('applies_to', 'categories')->update(['applies_to' => 'products']);

        Schema::dropIfExists('ecommerce_discount_contacts');
        Schema::dropIfExists('ecommerce_discount_categories');

        Schema::table('ecommerce_discounts', fn (Blueprint $table) => $table->dropColumn('customers'));
    }
};
