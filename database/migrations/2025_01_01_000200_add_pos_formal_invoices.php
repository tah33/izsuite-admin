<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase D4: the formal invoice (section 18.18). A POS sale's own
 * invoice can be given the business's invoice number - never a second sale -
 * its receipt number kept on the order: the order keeps when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->timestamp('invoiced_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', fn (Blueprint $table) => $table->dropColumn('invoiced_at'));
    }
};
