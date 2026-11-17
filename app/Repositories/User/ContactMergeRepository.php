<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmDuplicateReview;
use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The queries of merging one contact into another (Doc 17 Phase C3): the
 * records that move with it - its leads, opportunities, attachments, price
 * lists and the decisions about it as a possible duplicate - and the
 * financial records that stay with it.
 */
class ContactMergeRepository
{
    /** The financial records that stay with a merged contact, by the table that holds each. */
    private const STAYING = [
        'sales_documents', 'sales_payments', 'customer_advances', 'purchases', 'supplier_payments', 'expenses', 'expense_payments',
    ];

    /** The business's two contacts, locked until the merge is committed, by id - one missing is not the business's. */
    public function lockPair(Workspace $workspace, int $keptId, int $mergedId): Collection
    {
        return Contact::where('workspace_id', $workspace->id)
            ->whereIn('id', [$keptId, $mergedId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /** @return array<string, int> How many of each financial record name $contact: they stay with it. */
    public function staying(Contact $contact): array
    {
        $counts = [];

        foreach (self::STAYING as $table) {
            $counts[$table] = DB::table($table)->where('contact_id', $contact->id)->count();
        }

        return $counts;
    }

    /** @return array<string, int> How many of each record would move from $from to $to. */
    public function moving(Contact $from, Contact $to): array
    {
        return [
            'leads'               => DB::table('crm_leads')->where('contact_id', $from->id)->count(),
            'opportunities'       => DB::table('crm_opportunities')->where('contact_id', $from->id)->count(),
            // Doc 17 Phase E1.
            'activities'          => DB::table('crm_activities')->where('contact_id', $from->id)->count(),
            'attachments'         => $this->files($from)->count(),
            'price_lists'         => $this->priceLists($from, $to)->count(),
            'duplicate_decisions' => $this->decisions($from, $to)->count(),
        ];
    }

    /**
     * Moves them to $to, leaving their own times as they were - and drops
     * what $to already has: a place on the same price list, a decision of
     * the same lead.
     */
    public function move(Contact $from, Contact $to): void
    {
        DB::table('crm_leads')->where('contact_id', $from->id)->update(['contact_id' => $to->id]);
        DB::table('crm_opportunities')->where('contact_id', $from->id)->update(['contact_id' => $to->id]);
        DB::table('crm_activities')->where('contact_id', $from->id)->update(['contact_id' => $to->id]);
        $this->files($from)->update(['attachable_id' => $to->id]);

        $this->priceLists($from, $to)->update(['contact_id' => $to->id]);
        DB::table('price_list_contacts')->where('contact_id', $from->id)->delete();

        $this->decisions($from, $to)->update(['other_id' => $to->id]);
        $this->reviewsOf($from)->delete();
    }

    /** Archived, saying which it was merged into, when and by whom. */
    public function markMerged(Contact $merged, Contact $kept, User $user): void
    {
        $merged->forceFill([
            'is_active'      => false,
            'merged_into_id' => $kept->id,
            'merged_at'      => now(),
            'merged_by'      => $user->id,
        ])->save();
    }

    private function files(Contact $contact): Builder
    {
        return DB::table('workspace_files')->where('attachable_type', $contact->getMorphClass())->where('attachable_id', $contact->id);
    }

    /**
     * $from's places on price lists $to is not on. Those $to is on are read
     * first: MySQL does not change a table by a subquery of the same table.
     */
    private function priceLists(Contact $from, Contact $to): Builder
    {
        return DB::table('price_list_contacts')
            ->where('contact_id', $from->id)
            ->whereNotIn('price_list_id', DB::table('price_list_contacts')->where('contact_id', $to->id)->pluck('price_list_id')->all());
    }

    /** The decisions about $from as a lead's possible duplicate, of leads with none about $to - read first, as above. */
    private function decisions(Contact $from, Contact $to): Builder
    {
        return $this->reviewsOf($from)->whereNotIn('crm_lead_id', $this->reviewsOf($to)->pluck('crm_lead_id')->all());
    }

    private function reviewsOf(Contact $contact): Builder
    {
        return DB::table('crm_duplicate_reviews')->where('other_type', CrmDuplicateReview::CONTACT)->where('other_id', $contact->id);
    }
}
