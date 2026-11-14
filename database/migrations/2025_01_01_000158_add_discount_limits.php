<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase F2 (section 16.20): the largest discount a role may give, as a
 * percentage - none for no limit - and why a sales document's discount went
 * past it, when someone allowed to took it further.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_roles', function (Blueprint $table) {
            $table->decimal('max_discount', 5, 2)->nullable()->after('full_access');
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('discount_reason', 500)->nullable()->after('over_invoice_reason');
        });
    }

    public function down(): void
    {
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn('discount_reason'));
        Schema::table('workspace_roles', fn (Blueprint $table) => $table->dropColumn('max_discount'));
    }
};
