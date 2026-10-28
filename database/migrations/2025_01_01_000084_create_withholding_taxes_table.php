<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax (Doc 11 section 12): what a customer keeps back from a
 * payment and pays to the tax office for the business - or what the business
 * keeps back from a supplier.
 *
 * - workspaces.withholding_tax: Settings > Taxes' "Enable Withholding Tax"
 *   (section 9). It stands apart from the sales tax choice: a business that
 *   charges no VAT can still have tax withheld from what it is paid.
 * - withholding_taxes: the rates - each with the code it is known by (upper
 *   case, used once in the business), its percentage, what it applies to, and
 *   the dates it is in effect. A rate used on a payment cannot be deleted and
 *   keeps its percentage (see WithholdingTaxService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->boolean('withholding_tax')->default(false)->after('tax_rounding');
        });

        Schema::create('withholding_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->decimal('rate', 7, 4);                    // a percentage: 5.0000
            $table->boolean('for_customer_payments')->default(false);
            $table->boolean('for_supplier_payments')->default(false);
            $table->boolean('for_expense_payments')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_taxes');

        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropColumn('withholding_tax');
        });
    }
};
