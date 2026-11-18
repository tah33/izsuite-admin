<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase D1: the tax on izSuite's subscription invoices (Doc 13
 * section 16, Doc 15 section 16), separate from the businesses' own taxes.
 * One rule per country from a day on - a later one takes over from its day -
 * and whether a buyer's tax ID turns it into a reverse charge. Each invoice
 * keeps the tax it was issued with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_taxes', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2);
            $table->string('name', 50);
            $table->decimal('rate', 6, 3);
            $table->date('starts_on');
            $table->boolean('reverse_charge')->default(false);
            $table->string('reverse_charge_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['country', 'starts_on']);
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->string('tax_name', 50)->nullable()->after('credit');
            $table->decimal('tax_rate', 6, 3)->nullable()->after('tax_name');
            $table->decimal('tax', 12, 2)->default(0)->after('tax_rate');
            $table->string('tax_note', 255)->nullable()->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_name', 'tax_rate', 'tax', 'tax_note']);
        });

        Schema::dropIfExists('subscription_taxes');
    }
};
