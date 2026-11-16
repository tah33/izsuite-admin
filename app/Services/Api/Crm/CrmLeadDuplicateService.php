<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmDuplicateReview;
use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmDuplicateReviewRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Services\Crm\LeadDuplicates;
use App\Services\Crm\LeadVisibility;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reviewing a lead's possible duplicates (Doc 17 section 17.21 - Phase C2):
 * the list, and a decision about one - ignored with a reason, linked, or
 * taken back. Between two leads a decision counts on both. Each is logged
 * on the lead.
 */
class CrmLeadDuplicateService
{
    public function __construct(
        protected CrmLeadService $leadService,
        protected CrmLeadRepository $leads,
        protected ContactRepository $contacts,
        protected CrmDuplicateReviewRepository $reviews,
        protected LeadDuplicates $duplicates,
        protected LeadVisibility $visibility,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace, User $user, int $id): array
    {
        return $this->duplicates->of($workspace, $user, $this->leadService->find($workspace, $user, $id));
    }

    /** $data is validated (see CrmLeadDuplicateRequest): type, id and - to ignore - a reason. */
    public function decide(Workspace $workspace, User $user, int $id, string $decision, array $data): array
    {
        $lead  = $this->leadService->find($workspace, $user, $id);
        $other = $this->other($workspace, $user, $lead, $data['type'], (int) $data['id']);

        DB::transaction(function () use ($workspace, $user, $lead, $data, $decision) {
            $reason = $decision === CrmDuplicateReview::IGNORED ? $data['reason'] : null;

            $this->reviews->decide($workspace, $lead->id, $data['type'], (int) $data['id'], $decision, $reason, $user->id);

            if ($data['type'] === CrmDuplicateReview::LEAD) {
                $this->reviews->decide($workspace, (int) $data['id'], CrmDuplicateReview::LEAD, $lead->id, $decision, $reason, $user->id);
            }
        });

        ActivityLogService::record('updated', $decision === CrmDuplicateReview::LINKED
            ? 'Linked '.$this->leadService->describe($lead)." to {$other}"
            : "Marked {$other} as not a duplicate of ".$this->leadService->describe($lead), $lead, array_filter([
                'source' => 'api', 'duplicate' => "{$data['type']}:{$data['id']}", 'decision' => $decision, 'reason' => $data['reason'] ?? null,
            ]));

        return $this->duplicates->of($workspace, $user, $lead);
    }

    /** A decision taken back: the match is flagged again. */
    public function clear(Workspace $workspace, User $user, int $id, array $data): array
    {
        $lead  = $this->leadService->find($workspace, $user, $id);
        $other = $this->other($workspace, $user, $lead, $data['type'], (int) $data['id']);

        DB::transaction(function () use ($lead, $data) {
            $this->reviews->clear($lead->id, $data['type'], (int) $data['id']);

            if ($data['type'] === CrmDuplicateReview::LEAD) {
                $this->reviews->clear((int) $data['id'], CrmDuplicateReview::LEAD, $lead->id);
            }
        });

        ActivityLogService::record('updated', "Took back the decision about {$other} on ".$this->leadService->describe($lead), $lead, [
            'source' => 'api', 'duplicate' => "{$data['type']}:{$data['id']}",
        ]);

        return $this->duplicates->of($workspace, $user, $lead);
    }

    /** What the log calls the other record - one the user may see, not $lead itself: 'lead "Rahim Uddin"', 'contact "Uddin Traders"'. */
    private function other(Workspace $workspace, User $user, CrmLead $lead, string $type, int $id): string
    {
        if ($type === CrmDuplicateReview::LEAD) {
            $other = $id === $lead->id ? null : $this->leads->visibleAmong($workspace, $this->visibility->scopeFor($workspace, $user), [$id])->first();

            return $other ? $this->leadService->describe($other) : throw ValidationException::withMessages(['id' => 'Choose one of the leads you see.']);
        }

        $contact = $this->access->can('contacts.view') ? $this->contacts->findFor($workspace, $id) : null;

        return $contact ? 'contact "'.Str::limit($contact->name, 100).'"' : throw ValidationException::withMessages(['id' => 'Choose one of your contacts.']);
    }
}
