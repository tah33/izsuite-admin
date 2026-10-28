<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How each kind of document is numbered (Doc 11 section 21, Doc 14 section
 * 14.6): its prefix, the number it starts at, how many digits the number has,
 * and whether it carries the calendar year, financial year or month - starting
 * again from the first number with each one. One row per business and document
 * type, made when the business first changes that type's defaults (see
 * DocumentNumbering).
 *
 * The numbers themselves come from workspace_sequences, one counter per type,
 * prefix and period ("invoice|INV-|2026"). That name outgrows the 30
 * characters contact and item counters needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_numbering', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('prefix', 20)->default('');
            $table->unsignedInteger('starting_number')->default(1);
            $table->unsignedTinyInteger('number_length')->default(5);
            $table->string('period', 20)->default('none');   // none | year | financial_year | month
            $table->timestamps();

            $table->unique(['workspace_id', 'document_type']);
        });

        Schema::table('workspace_sequences', function (Blueprint $t) {
            $t->string('name', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_sequences', function (Blueprint $t) {
            $t->string('name', 30)->change();
        });

        Schema::dropIfExists('document_numbering');
    }
};
