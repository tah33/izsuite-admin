<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H4b - a request for a quotation becomes a CRM lead (section
 * 19.30):
 *
 * - ecommerce_settings.quote_requests_to_leads: whether, while CRM is on, a
 *   request also becomes a lead (on);
 * - ecommerce_quote_requests.crm_lead_id: the lead it became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->boolean('quote_requests_to_leads')->default(true)->after('abandoned_hours');
        });

        Schema::table('ecommerce_quote_requests', function (Blueprint $table) {
            $table->foreignId('crm_lead_id')->nullable()->after('storefront_customer_id')->constrained('crm_leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_quote_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('crm_lead_id'));
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn('quote_requests_to_leads'));
    }
};
