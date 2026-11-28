<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H4c - a request for a quotation becomes a draft quotation
 * (section 19.30):
 *
 * - ecommerce_settings.quote_requests_become: what a request also becomes -
 *   nothing more, a CRM lead (while CRM is on) or a draft quotation - in
 *   place of Phase H4b's quote_requests_to_leads, each business's carried
 *   over (on: a lead; off: nothing more);
 * - ecommerce_quote_requests.sales_document_id: the draft quotation it
 *   became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->string('quote_requests_become', 20)->default('lead')->after('abandoned_hours');
        });

        DB::table('ecommerce_settings')->where('quote_requests_to_leads', false)->update(['quote_requests_become' => 'nothing']);

        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn('quote_requests_to_leads'));

        Schema::table('ecommerce_quote_requests', function (Blueprint $table) {
            $table->foreignId('sales_document_id')->nullable()->after('crm_lead_id')->constrained('sales_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_quote_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_document_id'));

        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->boolean('quote_requests_to_leads')->default(true)->after('abandoned_hours');
        });

        DB::table('ecommerce_settings')->where('quote_requests_become', '!=', 'lead')->update(['quote_requests_to_leads' => false]);

        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn('quote_requests_become'));
    }
};
