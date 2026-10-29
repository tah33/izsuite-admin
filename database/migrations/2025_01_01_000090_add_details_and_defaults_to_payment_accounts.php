<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What else Doc 05 keeps on a payment account (sections 4 to 9):
 *
 * - location_id: the location it belongs to - none for the whole business. A
 *   location an account belongs to is not deleted (see LocationService); the
 *   database only clears it when the whole business goes.
 * - holder_name: the account holder or registered name.
 * - Its type's own details: a bank's branch, SWIFT / BIC / routing code and
 *   country; a wallet's merchant or till number; a gateway's merchant name
 *   and settlement currency. bank_name stays the bank or provider, and
 *   account_number the account number, IBAN, wallet number or reference.
 * - payment_instructions, and show_on_documents: whether its public details
 *   print on proformas, invoices and statements.
 * - allow_customer_payments / allow_supplier_payments: what may be recorded
 *   in it - customer payments and refunds, or supplier and expense payments.
 * - default_receiving / default_payment: the account each kind of payment
 *   starts with, one of each per currency and location.
 *
 * The "card" type becomes "payment_gateway", Doc 05's name for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $t) {
            $t->foreignId('location_id')->nullable()->after('currency')->constrained()->nullOnDelete();
            $t->string('holder_name')->nullable()->after('bank_name');
            $t->string('branch')->nullable()->after('account_number');
            $t->string('swift_code', 50)->nullable()->after('branch');
            $t->char('bank_country', 2)->nullable()->after('swift_code');
            $t->string('merchant_name')->nullable()->after('bank_country');
            $t->string('merchant_number', 50)->nullable()->after('merchant_name');
            $t->char('settlement_currency', 3)->nullable()->after('merchant_number');
            $t->text('payment_instructions')->nullable()->after('opening_balance_date');
            $t->boolean('show_on_documents')->default(false)->after('payment_instructions');
            $t->boolean('allow_customer_payments')->default(true)->after('show_on_documents');
            $t->boolean('allow_supplier_payments')->default(true)->after('allow_customer_payments');
            $t->boolean('default_receiving')->default(false)->after('allow_supplier_payments');
            $t->boolean('default_payment')->default(false)->after('default_receiving');
        });

        DB::table('payment_accounts')->where('type', 'card')->update(['type' => 'payment_gateway']);
    }

    public function down(): void
    {
        DB::table('payment_accounts')->where('type', 'payment_gateway')->update(['type' => 'card']);

        Schema::table('payment_accounts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('location_id');
            $t->dropColumn([
                'holder_name', 'branch', 'swift_code', 'bank_country', 'merchant_name', 'merchant_number', 'settlement_currency',
                'payment_instructions', 'show_on_documents', 'allow_customer_payments', 'allow_supplier_payments', 'default_receiving', 'default_payment',
            ]);
        });
    }
};
