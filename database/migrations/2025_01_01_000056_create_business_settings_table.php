<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per account: the business the workspace runs, plus its tax and
 * accounting defaults. The frontend's Settings > Business form saves into this
 * table as a single form, so it is a single table too - column names match the
 * form's field names one to one.
 *
 * Unlike the users table there are no legacy rows to protect, so the fields
 * the form marks required are NOT NULL here as well. The row is only ever
 * written by that form, which sends every required field at once.
 *
 * currency and language hold codes (ISO 4217 / ISO 639) rather than ids into
 * the currencies and languages tables: those are admin-managed and rows can be
 * renamed or removed, and a stored code stays meaningful either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Business details
            $table->string('business_name');
            $table->date('start_date')->nullable();
            $table->string('currency', 3);
            $table->string('language', 10);
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->string('business_contact_number', 30)->nullable();
            $table->string('alternate_contact_number', 30)->nullable();
            $table->string('country', 100);
            $table->string('state', 100);
            $table->string('city', 100);
            $table->string('zip_code', 20);
            $table->string('landmark');
            $table->string('timezone', 64);

            // Tax registration - up to two, e.g. GST and a state VAT
            $table->string('tax_1_name', 50)->nullable();
            $table->string('tax_1_no', 50)->nullable();
            $table->string('tax_2_name', 50)->nullable();
            $table->string('tax_2_no', 50)->nullable();

            // Accounting defaults
            $table->unsignedTinyInteger('financial_year_start_month'); // 1 = January ... 12 = December
            $table->enum('stock_accounting_method', ['fifo', 'lifo', 'weighted_average', 'specific_identification']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
