<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A location needs only its name; country and state are optional.
 *
 * The API has always accepted a location without them, and a new account's
 * "Main Location" is made with neither, since registration asks for neither -
 * but the columns were created NOT NULL, so both failed. Lengths stay as they
 * were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->change();
            $table->string('state', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows saved without them would block going back, so they get an
        // empty string first.
        DB::table('locations')->whereNull('country')->update(['country' => '']);
        DB::table('locations')->whereNull('state')->update(['state' => '']);

        Schema::table('locations', function (Blueprint $table) {
            $table->string('country', 100)->nullable(false)->change();
            $table->string('state', 100)->nullable(false)->change();
        });
    }
};
