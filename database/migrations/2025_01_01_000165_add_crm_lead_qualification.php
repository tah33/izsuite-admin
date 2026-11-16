<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase B2a: a lead's qualification (section 17.10) and the items or
 * services it is interested in (section 17.6), and Settings > CRM's say on
 * what must be filled in before a lead is qualified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->text('customer_need')->nullable()->after('notes');
            $table->decimal('budget', 15, 2)->nullable()->after('customer_need');               // in the lead's currency
            $table->string('purchase_period', 20)->nullable()->after('budget');                // this_month | within_3_months | within_6_months | within_12_months | later
            $table->string('decision_maker')->nullable()->after('purchase_period');
            $table->string('business_size', 100)->nullable()->after('decision_maker');         // business size or type
            $table->string('next_action')->nullable()->after('business_size');
            $table->text('qualification_notes')->nullable()->after('next_action');
        });

        Schema::create('crm_lead_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();           // none: a name typed in
            $table->string('name');                                                              // the item's name as it was, or the one typed
            $table->decimal('quantity', 15, 3)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('crm_settings', function (Blueprint $table) {
            $table->json('qualification_required')->nullable()->after('mode');                 // null: the default, the customer need
        });
    }

    public function down(): void
    {
        Schema::table('crm_settings', fn (Blueprint $table) => $table->dropColumn('qualification_required'));
        Schema::dropIfExists('crm_lead_items');
        Schema::table('crm_leads', fn (Blueprint $table) => $table->dropColumn(['customer_need', 'budget', 'purchase_period', 'decision_maker', 'business_size', 'next_action', 'qualification_notes']));
    }
};
