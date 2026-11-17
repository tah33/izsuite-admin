<?php

namespace App\Repositories\User;

use App\Models\Shared\ActivityLog;
use App\Models\Shared\RecordComment;
use App\Models\User\CrmActivity;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the timeline of a lead, an opportunity or a contact is made of (Doc
 * 17 section 17.18 - Phase E3), each read newest first up to a limit: the
 * Activity Log entries about the records, the activities done or cancelled
 * for them, and the comments on them. Sales documents come through
 * OpportunityDocuments::madeFor().
 */
class CrmTimelineRepository
{
    /**
     * The Activity Log entries about the records of $about - their ids by
     * model class - each with who made it. Those of comments and of
     * activities are left out: the timeline shows the comments and the
     * activities themselves. $files keeps only the entries of files (true),
     * leaves them out (false), or both (null).
     *
     * @param  array<class-string, list<int>>  $about
     */
    public function logEntries(int $workspaceId, array $about, ?bool $files, int $limit): Collection
    {
        $about = array_filter($about);

        if ($about === []) {
            return collect();
        }

        return ActivityLog::query()
            ->where('workspace_id', $workspaceId)
            ->where(function (Builder $query) use ($about) {
                foreach ($about as $type => $ids) {
                    $query->orWhere(fn (Builder $query) => $query->where('model_type', $type)->whereIn('model_id', $ids));
                }
            })
            ->whereNull('properties->comment_id')
            ->whereNull('properties->activity_id')
            ->when($files === true, fn (Builder $query) => $query->whereNotNull('properties->file'))
            ->when($files === false, fn (Builder $query) => $query->whereNull('properties->file'))
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The activities completed or cancelled for the records of $for - their
     * ids by the activity's column (lead_id, opportunity_id, contact_id) -
     * the latest ended first, with their type, who ended them and what they
     * are for.
     *
     * @param  array<string, list<int>>  $for
     */
    public function endedActivities(Workspace $workspace, array $for, int $limit): Collection
    {
        $for = array_filter($for);

        if ($for === []) {
            return collect();
        }

        return CrmActivity::query()
            ->where('workspace_id', $workspace->id)
            ->where(function (Builder $query) use ($for) {
                foreach ($for as $column => $ids) {
                    $query->orWhereIn($column, $ids);
                }
            })
            ->whereIn('status', [CrmActivity::COMPLETED, CrmActivity::CANCELLED])
            ->with(['type:id,key,name', 'completer', 'canceller'])
            ->orderByRaw('COALESCE(completed_at, cancelled_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The comments on the records of $on - their ids by model class - the
     * newest first, each with its author.
     *
     * @param  array<class-string, list<int>>  $on
     */
    public function comments(int $workspaceId, array $on, int $limit): Collection
    {
        $on = array_filter($on);

        if ($on === []) {
            return collect();
        }

        return RecordComment::query()
            ->where('workspace_id', $workspaceId)
            ->where(function (Builder $query) use ($on) {
                foreach ($on as $type => $ids) {
                    $query->orWhere(fn (Builder $query) => $query->where('commentable_type', $type)->whereIn('commentable_id', $ids));
                }
            })
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

}
