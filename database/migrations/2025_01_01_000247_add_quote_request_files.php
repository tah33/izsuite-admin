<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H4d - files with a request for a quotation (section 19.30):
 * ecommerce_settings.quote_request_files, whether a customer may attach
 * files to one (off). The files themselves are workspace_files of the
 * request, as any record's attachments are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->boolean('quote_request_files')->default(false)->after('quote_requests_become');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn('quote_request_files'));
    }
};
