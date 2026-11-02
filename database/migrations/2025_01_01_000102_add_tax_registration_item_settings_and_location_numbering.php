<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of Doc 11 Phase D:
 *
 * - The business's tax registration (section 9): registered or not, and the
 *   country it is registered in - beside the tax numbers it already keeps.
 * - Item settings (section 18): whether items use warranty fields - they did,
 *   so they still do - the business's own units of measure and the one new
 *   items start with, SKUs given automatically with a prefix, and the format
 *   barcodes are checked against. None of it changes a saved item.
 * - Numbering with the location's code (section 21), off for every type.
 *
 * The sidebar says whether warranties are used, so the cached ones are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('tax_registration_status', 20)->nullable()->after('timezone');
            $table->string('tax_country', 2)->nullable()->after('tax_registration_status');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('warranty_enabled')->default(true)->after('brands_enabled');
            $table->string('default_unit', 20)->nullable()->after('warranty_enabled');
            $table->json('units')->nullable()->after('default_unit');
            $table->boolean('sku_auto')->default(false)->after('units');
            $table->string('sku_prefix', 10)->nullable()->default('SKU-')->after('sku_auto');
            $table->string('barcode_format', 10)->default('any')->after('sku_prefix');
        });

        Schema::table('document_numbering', function (Blueprint $table) {
            $table->boolean('location_code')->default(false)->after('period');
        });

        CatalogVersion::bump();
    }

    public function down(): void
    {
        Schema::table('document_numbering', function (Blueprint $table) {
            $table->dropColumn('location_code');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['warranty_enabled', 'default_unit', 'units', 'sku_auto', 'sku_prefix', 'barcode_format']);
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['tax_registration_status', 'tax_country']);
        });
    }
};
