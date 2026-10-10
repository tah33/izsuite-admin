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
 * Of the details, only name is NOT NULL. It is all that identifies a staff
 * member; the rest are details a form may or may not ask for, and which of them
 * it requires is for that form's validation to say, not a column's. phone,
 * state and country are the same free text business_settings keeps, at the
 * same lengths.
 *
 * status is 1 for Active, 0 for Inactive - the two choices of the Staff form's
 * Status dropdown, kept as a number, not a word. NOT NULL and 1 unless said
 * otherwise: a staff member is one or the other, and a row added without saying
 * is a working one. Which values it may hold is for the API's validation to
 * enforce, as with the rest of this table.
 *
 * workspaces.staff_id is constrained here rather than in its own migration:
 * workspaces is created first, before this table exists.
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
            $table->unsignedTinyInteger('status')->default(1);

            $table->timestamps();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreign('staff_id')->references('id')->on('user_staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });

        Schema::dropIfExists('user_staff');
    }
};
