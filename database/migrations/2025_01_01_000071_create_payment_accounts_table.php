<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment accounts - the cash drawers, bank accounts and mobile wallets money
 * goes in and out of - are one shared record source (Doc 01 section 10). Each
 * uses one currency (section 11): the business's base currency or one of its
 * additional currencies.
 *
 * The Payment Accounts sidebar item appears once the first one exists
 * (section 6.2).
 *
 * They belong to the business: deleting the workspace deletes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);                       // cash | bank | mobile_money | card | other
            $table->string('name');
            $table->char('currency', 3);

            $table->string('bank_name')->nullable();          // the bank or provider
            $table->string('account_number', 50)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
