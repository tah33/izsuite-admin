<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staff an account keeps: the people who work for its business, with their
 * contact details.
 *
 * An account can have any number of them, so there is no unique constraint on
 * user_id; the foreign key still gives it an index, and a deleted account takes
 * its staff with it.
 *
 * Not the users table's staff role, which is accounts the admin panel manages:
 * a row here is a record the account owner keeps, and holds no credentials.
 *
 * Only name is NOT NULL. It is all that identifies a staff member; the rest are
 * details a form may or may not ask for, and which of them it requires is for
 * that form's validation to say, not a column's. phone, state and country are
 * the same free text business_settings keeps, at the same lengths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_staff');
    }
};
