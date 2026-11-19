<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase I3: marketing consent (section 17.25), kept apart from sales
 * follow-up. Each row is one answer a contact or lead gave - consent given
 * or refused - for one purpose (a Settings → CRM list) on one channel, with
 * where it came from, when, who recorded it, its expiry and its evidence. A
 * consent given can be withdrawn later: the row keeps both. Nothing is ever
 * deleted, so the history stays. A lead's go with it to its contact when it
 * is converted: the row then names both.
 *
 * Businesses whose CRM lists started already get the consent purposes they
 * start with.
 */
return new class extends Migration
{
    /** The consent purposes a business starts with, by izSuite's key: CrmOption::STARTER's, as they were. */
    private const PURPOSES = [
        'promotions' => 'Promotions and Offers', 'newsletters' => 'Newsletters', 'product_updates' => 'Product Updates',
        'events' => 'Events and Invitations', 'feedback' => 'Feedback and Surveys',
    ];

    public function up(): void
    {
        Schema::create('marketing_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Whose answer it is: a contact's, a lead's, or a converted lead's - both.
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();

            $table->foreignId('purpose_id')->constrained('crm_options');
            $table->string('channel', 20);                                                  // email | sms | messaging | phone
            $table->string('status', 20);                                                   // given | refused | withdrawn
            $table->string('source', 20);                                                   // web_form | in_person | phone | email | paper | event | import | other
            $table->timestamp('recorded_at');                                               // when it was given or refused
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expires_on')->nullable();                                         // a consent given, where it lapses
            $table->string('evidence')->nullable();                                         // supporting evidence or a reference
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('withdrawal_note')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'purpose_id', 'channel']);
            $table->index(['lead_id', 'purpose_id', 'channel']);
        });

        $now = now();

        foreach (DB::table('crm_options')->distinct()->orderBy('workspace_id')->pluck('workspace_id') as $workspaceId) {
            $position = 0;

            foreach (self::PURPOSES as $key => $name) {
                DB::table('crm_options')->insertOrIgnore([
                    'workspace_id' => $workspaceId, 'kind' => 'consent_purpose', 'key' => $key, 'name' => $name, 'position' => ++$position,
                    'is_active'    => true, 'needs_outcome' => false, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_consents');

        DB::table('crm_options')->where('kind', 'consent_purpose')->delete();
    }
};
