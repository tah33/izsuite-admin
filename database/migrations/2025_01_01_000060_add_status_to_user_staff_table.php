<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a staff member is active: 1 for Active, 0 for Inactive - the two
 * choices of the Staff form's Status dropdown, kept as a number, not a word.
 *
 * NOT NULL, and 1 unless said otherwise: a staff member is one or the other,
 * and a row added without saying - or one that was here before this column was -
 * is a working one. Which values it may hold is for the API's validation to
 * enforce, as with the rest of this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_staff', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(1)->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('user_staff', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
