<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses Lite (Doc 10): what a business spends on running itself.
 *
 * - expense_settings: Settings > Expenses (Doc 11 section 17) - the payment
 *   terms a payee without their own has, the notes a new expense starts with,
 *   and whether recording one checks for a receipt entered before. One row
 *   per business, made when first saved.
 * - expense_categories: the business's one shared list (Doc 10 section 7),
 *   each with the tax its lines start with. Every business starts with the
 *   list Doc 10 section 2 names - its own to rename, switch off or add to;
 *   the businesses here already get it below.
 * - expenses: the header. The payee is a contact, or a name typed in for the
 *   one expense (payee_name) - which never becomes a contact. A draft has no
 *   number; it gets one when it is recorded, and keeps the business and the
 *   payee as they were then. Totals are SalesCalculator's, in the expense's
 *   currency and, at its exchange rate, in the base currency. An exact
 *   duplicate recorded anyway keeps why (duplicate_reason).
 * - expense_lines: a category, a description, a quantity and an amount each,
 *   with the category's name and the tax as they were when the line was
 *   saved - recoverable or not, for the Tax Summary.
 * - tax_rates.default_for_expenses marks the business's default expense tax
 *   (Doc 11 section 11), as its sales and purchase defaults are marked.
 *
 * Contacts, categories, tax rates and locations an expense uses cannot be
 * deleted (see their services); the nullOnDelete foreign keys only let a
 * business be deleted with everything in it.
 */
return new class extends Migration
{
    /** Doc 10 section 2's categories, as the businesses already here start with them. */
    private const STARTER = ['Rent', 'Utilities', 'Transport', 'Communication', 'Marketing', 'Office Costs', 'Professional Services', 'Bank Charges', 'Repairs', 'Meals and Travel'];

    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->boolean('default_for_expenses')->default(false)->after('default_for_purchases');
        });

        Schema::create('expense_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('payment_terms')->default(30);
            $table->text('notes')->nullable();
            $table->boolean('duplicate_check')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();   // the tax its lines start with
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('status', 20)->default('draft');              // draft | recorded | void
            $table->string('payment_status', 20)->default('not_applicable');
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payee_name')->nullable();                    // a payee typed in, when not a contact
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date');                               // the expense date
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('payment_terms')->nullable();   // the days the due date came from
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8)->default(1);         // base currency per unit of `currency`
            $table->string('price_tax_mode', 10)->default('exclusive');
            $table->string('tax_rounding', 10)->default('line');
            $table->string('receipt_number', 100)->nullable();           // the payee's receipt or invoice number
            $table->string('reference', 100)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('base_grand_total', 15, 2)->default(0);
            $table->decimal('base_tax_total', 15, 2)->default(0);
            $table->decimal('base_net_total', 15, 2)->default(0);
            $table->json('taxes')->nullable();                           // the tax breakdown, one entry per rate
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('business_snapshot')->nullable();
            $table->json('payee_snapshot')->nullable();
            $table->json('template_snapshot')->nullable();
            $table->string('status_reason', 500)->nullable();            // why it was voided
            $table->string('duplicate_reason', 500)->nullable();         // why an exact duplicate was recorded
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'document_date']);
            $table->index(['workspace_id', 'contact_id']);
        });

        Schema::create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);                        // the unit amount
            $table->decimal('amount', 15, 2)->default(0);                // quantity × unit amount
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tax_name', 100)->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->string('tax_treatment', 20)->nullable();
            $table->string('tax_recoverability', 20)->nullable();        // recoverable | non_recoverable
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('workspaces')->orderBy('id')->pluck('id')->chunk(100)->each(fn ($ids) => DB::table('expense_categories')->insert(
            $ids->flatMap(fn (int $id) => array_map(fn (string $name) => [
                'workspace_id' => $id,
                'name'         => $name,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], self::STARTER))->all(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('expense_settings');

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn('default_for_expenses');
        });
    }
};
