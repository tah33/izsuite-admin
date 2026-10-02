<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The places an account's business operates from - branches, warehouses,
 * outlets - listed under Settings > Locations in the frontend.
 *
 * An account can have any number of them, so unlike business_settings there is
 * no unique constraint on user_id; the foreign key still gives it an index, and
 * a deleted account takes its locations with it.
 *
 * Every field is NOT NULL: a location with no name, country or state is not one
 * anybody could pick from a list. country and state are the same free text
 * business_settings keeps, at the same lengths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('country', 100);
            $table->string('state', 100);
            $table->string('name');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
