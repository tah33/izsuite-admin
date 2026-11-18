<?php

use App\Support\LeadMatchKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase C2: possible duplicate leads (section 17.21). Each lead keeps
 * what it is compared on - its e-mail, phone and business name, tidied - and
 * each decision about a possible duplicate is kept: ignored with a reason,
 * or linked. Between two leads a decision is kept on both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('match_email')->nullable()->after('phone');
            $table->string('match_phone', 20)->nullable()->after('match_email');
            $table->string('match_business')->nullable()->after('match_phone');

            $table->index(['workspace_id', 'match_email']);
            $table->index(['workspace_id', 'match_phone']);
            $table->index(['workspace_id', 'match_business']);
        });

        // The leads there are already.
        DB::table('crm_leads')->orderBy('id')->select(['id', 'email', 'phone', 'business_name'])->each(function ($lead) {
            DB::table('crm_leads')->where('id', $lead->id)->update(LeadMatchKeys::of($lead->email, $lead->phone, $lead->business_name));
        });

        Schema::create('crm_duplicate_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_lead_id')->constrained()->cascadeOnDelete();
            $table->string('other_type', 10);                   // lead | contact
            $table->unsignedBigInteger('other_id');
            $table->string('decision', 10);                     // ignored | linked
            $table->string('reason', 500)->nullable();          // why ignored
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['crm_lead_id', 'other_type', 'other_id']);
            $table->index(['other_type', 'other_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_duplicate_reviews');

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'match_email']);
            $table->dropIndex(['workspace_id', 'match_phone']);
            $table->dropIndex(['workspace_id', 'match_business']);
            $table->dropColumn(['match_email', 'match_phone', 'match_business']);
        });
    }
};
