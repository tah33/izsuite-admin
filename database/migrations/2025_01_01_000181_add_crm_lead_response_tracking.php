<?php

use App\Models\User\CrmLead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase G3a: when a lead was first given an owner and first contacted
 * (section 17.23). Its assigned_at and contacted_at are the latest: they move
 * on a second assignment or contact. Its first response is the first time
 * anyone answered it - marked it contacted, completed an activity for it or
 * sent it an e-mail.
 *
 * Leads that exist get theirs from what they kept: an assignment logged with
 * no previous owner is the first; one logged with a previous owner means it
 * was added with one. Their first contact is the earliest of their markings,
 * activities done and e-mails sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->timestamp('first_assigned_at')->nullable()->after('assigned_at');
            $table->timestamp('first_contacted_at')->nullable()->after('contacted_at');
        });

        DB::table('crm_leads')->select(['id', 'created_at', 'assigned_at', 'contacted_at'])->orderBy('id')->chunkById(200, function ($leads) {
            $ids = $leads->pluck('id')->all();

            $logs = DB::table('activity_logs')
                ->where('model_type', CrmLead::class)
                ->whereIn('model_id', $ids)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['model_id', 'properties', 'created_at'])
                ->groupBy('model_id');
            $activities = DB::table('crm_activities')->whereIn('lead_id', $ids)->where('status', 'completed')
                ->groupBy('lead_id')->selectRaw('lead_id, min(completed_at) as first')->pluck('first', 'lead_id');
            $emails = DB::table('crm_emails')->whereIn('lead_id', $ids)
                ->groupBy('lead_id')->selectRaw('lead_id, min(created_at) as first')->pluck('first', 'lead_id');

            foreach ($leads as $lead) {
                $entries = collect($logs->get($lead->id, []))->map(fn ($entry) => ['at' => $entry->created_at, 'properties' => json_decode($entry->properties ?? 'null', true) ?: []]);
                $assigns = $entries->filter(fn (array $entry) => array_key_exists('owner_id', $entry['properties']) && array_key_exists('previous_owner_id', $entry['properties']))->values();
                $marked  = $entries->first(fn (array $entry) => ($entry['properties']['status'] ?? null) === CrmLead::CONTACTED);

                $firstAssigned = match (true) {
                    // Added with its owner, never given to another; or given one once - that is its latest too.
                    $assigns->isEmpty(), $assigns->count() === 1 && $assigns[0]['properties']['previous_owner_id'] === null => $lead->assigned_at,
                    // Added with an owner, given to another since.
                    $assigns[0]['properties']['previous_owner_id'] !== null => $lead->created_at,
                    default                                                  => $assigns[0]['at'],
                };
                $firstContacted = collect([$marked['at'] ?? null, $lead->contacted_at, $activities[$lead->id] ?? null, $emails[$lead->id] ?? null])->filter()->min();

                if ($firstAssigned || $firstContacted) {
                    DB::table('crm_leads')->where('id', $lead->id)->update(['first_assigned_at' => $firstAssigned, 'first_contacted_at' => $firstContacted]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', fn (Blueprint $table) => $table->dropColumn(['first_assigned_at', 'first_contacted_at']));
    }
};
