<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A location's country and state become optional - only its name is required.
 *
 * Every account that registers is given a "Main Location" straight away (see
 * App\Listeners\CreateDefaultLocation), and registration asks for neither: the
 * owner fills them in later from Settings > Locations, if they want them at all.
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

    /**
     * Back to NOT NULL. A location saved without one in the meantime is given an
     * empty string first - the columns could not be changed back otherwise.
     */
    public function down(): void
    {
        DB::table('locations')->whereNull('country')->update(['country' => '']);
        DB::table('locations')->whereNull('state')->update(['state' => '']);

        Schema::table('locations', function (Blueprint $table) {
            $table->string('country', 100)->nullable(false)->change();
            $table->string('state', 100)->nullable(false)->change();
        });
    }
};
