<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items - the products and services the business sells or buys - are one
 * shared record source (Doc 01 section 10), like contacts.
 *
 * Prices are in the business's base currency. A SKU, when given, is unique
 * within the business (two businesses may both have "SKU-1"); stock levels
 * belong to the Inventory app, not here.
 *
 * They belong to the business: deleting the workspace deletes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('type', 10)->default('product');   // product | service
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->string('unit', 20)->nullable();            // pcs, kg, hour…

            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['workspace_id', 'sku']);
            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
