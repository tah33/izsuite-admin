<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 14 Phase E1: who and where from (sections 14.5 and 14.9).
 *
 * - Contacts, items, payment accounts and locations keep who added them and
 *   who last changed them. Transactions kept both already; a location's
 *   creator was its user_id until now.
 * - Each sales document, purchase, expense and payment keeps the app that
 *   made it. Everything so far was made by its family's Lite app.
 * - A sales document's, purchase's or expense's exchange rate keeps its date
 *   and where it came from: the business's own rates, or entered on the
 *   transaction. Those made before stay unknown.
 */
return new class extends Migration
{
    private const AUTHORED = ['contacts', 'items', 'payment_accounts', 'locations'];

    /** table => the family whose Lite app made its records so far */
    private const MADE = [
        'sales_documents'   => 'sales',
        'purchases'         => 'purchases',
        'expenses'          => 'expenses',
        'sales_payments'    => 'sales',
        'supplier_payments' => 'purchases',
        'expense_payments'  => 'expenses',
    ];

    private const RATED = ['sales_documents', 'purchases', 'expenses'];

    public function up(): void
    {
        foreach (self::AUTHORED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        DB::table('locations')->whereNull('created_by')->update(['created_by' => DB::raw('user_id')]);

        foreach (self::MADE as $name => $family) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('source_app_id')->nullable()->constrained('apps')->nullOnDelete();
            });

            $lite = DB::table('apps')->where('family_key', $family)->where('edition', 'lite')->value('id');

            if ($lite) {
                DB::table($name)->whereNull('source_app_id')->update(['source_app_id' => $lite]);
            }
        }

        foreach (self::RATED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->date('exchange_rate_date')->nullable()->after('exchange_rate');
                $table->string('exchange_rate_source', 20)->nullable()->after('exchange_rate_date');
            });
        }
    }

    public function down(): void
    {
        foreach (self::RATED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['exchange_rate_date', 'exchange_rate_source']);
            });
        }

        foreach (array_keys(self::MADE) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('source_app_id');
            });
        }

        foreach (self::AUTHORED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
