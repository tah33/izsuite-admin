<?php

namespace App\Repositories\User;

use App\Models\User\CrmDuplicateReview;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** Decisions about leads' possible duplicates (Doc 17 Phase C2). */
class CrmDuplicateReviewRepository
{
    /** The decisions about $leadIds' possible duplicates, each with who made it. */
    public function forLeads(array $leadIds): Collection
    {
        return $leadIds === [] ? new Collection : CrmDuplicateReview::query()
            ->whereIn('crm_lead_id', $leadIds)
            ->with('decider:id,first_name,last_name')
            ->get();
    }

    /** $leadId's decision about $type $otherId, made or changed. */
    public function decide(Workspace $workspace, int $leadId, string $type, int $otherId, string $decision, ?string $reason, int $userId): CrmDuplicateReview
    {
        return CrmDuplicateReview::query()->updateOrCreate(
            ['crm_lead_id' => $leadId, 'other_type' => $type, 'other_id' => $otherId],
            ['workspace_id' => $workspace->id, 'decision' => $decision, 'reason' => $reason, 'decided_by' => $userId],
        );
    }

    public function clear(int $leadId, string $type, int $otherId): void
    {
        CrmDuplicateReview::query()->where('crm_lead_id', $leadId)->where('other_type', $type)->where('other_id', $otherId)->delete();
    }
}
