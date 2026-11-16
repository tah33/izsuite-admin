<?php

namespace App\Services\Crm;

use App\Models\User\Contact;
use App\Models\User\CrmDuplicateReview;
use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmDuplicateReviewRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Services\Shared\WorkspaceAccess;
use App\Support\LeadMatchKeys;
use Illuminate\Support\Collection;

/**
 * A lead's possible duplicates (Doc 17 section 17.21 - Phase C2): other leads
 * with the same e-mail, phone or business name, and contacts matching as
 * conversion matches them - the same e-mail, phone, or business name (the
 * person's, for a lead with no business). Only leads the user sees count,
 * and contacts only for a role that may view them. A decision - ignored or
 * linked - takes a match off the lead's flag.
 */
class LeadDuplicates
{
    public function __construct(
        protected CrmLeadRepository $leads,
        protected ContactRepository $contacts,
        protected CrmDuplicateReviewRepository $reviews,
        protected LeadVisibility $visibility,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * $lead's possible duplicates and the decisions about them - undecided
     * first - each with what matched. One decided that no longer matches
     * stays listed.
     *
     * @return list<array<string, mixed>>
     */
    public function of(Workspace $workspace, User $user, CrmLead $lead): array
    {
        $scope    = $this->visibility->scopeFor($workspace, $user);
        $views    = $this->access->can('contacts.view');
        $reviews  = $this->reviews->forLeads([$lead->id])->keyBy(fn (CrmDuplicateReview $review) => "{$review->other_type}:{$review->other_id}");
        $leads    = $this->leads->matching($workspace, $scope, $this->keysOf(collect([$lead])), [$lead->id])->keyBy('id');
        $contacts = $views ? $this->contacts->matchingAny($workspace, ...$this->contactKeysOf(collect([$lead])))->keyBy('id') : collect();

        // Decided about, but no longer among the matches.
        $leads    = $leads->union($this->leads->visibleAmong($workspace, $scope, $this->decidedIds($reviews, CrmDuplicateReview::LEAD, $leads)));
        $contacts = $views ? $contacts->union($this->contacts->findManyFor($workspace, $this->decidedIds($reviews, CrmDuplicateReview::CONTACT, $contacts))) : $contacts;

        $rows = [
            ...$leads->map(fn (CrmLead $other) => $this->row(CrmDuplicateReview::LEAD, $other->id, $other->name, $other->business_name, $this->leadMatches($lead, $other), $reviews, [
                'status' => $other->status, 'owner' => $other->owner?->name,
            ]))->values(),
            ...$contacts->map(fn (Contact $contact) => $this->row(CrmDuplicateReview::CONTACT, $contact->id, $contact->name, $contact->contact_number, $this->contactMatches($lead, $contact), $reviews))->values(),
        ];

        $rows = array_values(array_filter($rows, fn (array $row) => $row['matched_on'] !== [] || $row['decision'] !== null));
        usort($rows, fn (array $a, array $b) => [$a['decision'] !== null, $a['decision'] === CrmDuplicateReview::IGNORED] <=> [$b['decision'] !== null, $b['decision'] === CrmDuplicateReview::IGNORED]);

        return $rows;
    }

    /**
     * Of $leads - a page of the list - which open ones have a possible
     * duplicate not decided about: id => bool.
     *
     * @return array<int, bool>
     */
    public function flagged(Workspace $workspace, User $user, Collection $leads): array
    {
        $open = $leads->filter(fn (CrmLead $lead) => $lead->isOpen())->values();

        if ($open->isEmpty()) {
            return [];
        }

        $others   = $this->leads->matching($workspace, $this->visibility->scopeFor($workspace, $user), $this->keysOf($open), []);
        $contacts = $this->access->can('contacts.view') ? $this->contacts->matchingAny($workspace, ...$this->contactKeysOf($open)) : collect();
        $reviewed = $this->reviews->forLeads($open->pluck('id')->all())->groupBy('crm_lead_id')
            ->map(fn (Collection $reviews) => $reviews->map(fn (CrmDuplicateReview $review) => "{$review->other_type}:{$review->other_id}")->all());

        return $open->mapWithKeys(fn (CrmLead $lead) => [$lead->id => $others->contains(fn (CrmLead $other) => $other->id !== $lead->id
                && $this->leadMatches($lead, $other) !== []
                && ! in_array("lead:{$other->id}", $reviewed[$lead->id] ?? [], true))
            || $contacts->contains(fn (Contact $contact) => $this->contactMatches($lead, $contact) !== []
                && ! in_array("contact:{$contact->id}", $reviewed[$lead->id] ?? [], true)),
        ])->all();
    }

    /** What two leads share. @return list<string> */
    public function leadMatches(CrmLead $lead, CrmLead $other): array
    {
        return array_keys(array_filter([
            'email'         => $lead->match_email && $lead->match_email === $other->match_email,
            'phone'         => $lead->match_phone && $lead->match_phone === $other->match_phone,
            'business_name' => $lead->match_business && $lead->match_business === $other->match_business,
        ]));
    }

    /** What a lead shares with a contact, as conversion compares them. @return list<string> */
    public function contactMatches(CrmLead $lead, Contact $contact): array
    {
        $theirs = array_filter([LeadMatchKeys::text($contact->name), LeadMatchKeys::text($contact->trading_name)]);
        $phones = array_filter([preg_replace('/\D/', '', (string) $contact->phone), preg_replace('/\D/', '', (string) $contact->alt_phone)]);

        return array_keys(array_filter([
            'name'  => array_intersect($this->namesOf($lead), $theirs) !== [],
            'email' => $lead->match_email && $lead->match_email === LeadMatchKeys::text($contact->email),
            'phone' => $lead->match_phone && collect($phones)->contains(fn (string $digits) => str_ends_with($digits, $lead->match_phone)),
        ]));
    }

    /** @return array{emails: list<string>, phones: list<string>, businesses: list<string>} */
    private function keysOf(Collection $leads): array
    {
        return [
            'emails'     => $leads->pluck('match_email')->filter()->unique()->values()->all(),
            'phones'     => $leads->pluck('match_phone')->filter()->unique()->values()->all(),
            'businesses' => $leads->pluck('match_business')->filter()->unique()->values()->all(),
        ];
    }

    /** What ContactRepository::matchingAny() takes for $leads: names, e-mails and phones. */
    private function contactKeysOf(Collection $leads): array
    {
        return [
            $leads->flatMap(fn (CrmLead $lead) => $this->namesOf($lead))->unique()->values()->all(),
            $leads->pluck('match_email')->filter()->unique()->values()->all(),
            $leads->pluck('match_phone')->filter()->unique()->values()->all(),
        ];
    }

    /** A lead's business - or its person, with none - and its person too, tidied. @return list<string> */
    private function namesOf(CrmLead $lead): array
    {
        return array_values(array_filter([LeadMatchKeys::text($lead->business_name ?: $lead->name), $lead->business_name ? LeadMatchKeys::text($lead->name) : null]));
    }

    /** The other records of $type a decision names that $found does not hold. @return list<int> */
    private function decidedIds(Collection $reviews, string $type, Collection $found): array
    {
        return $reviews->where('other_type', $type)->pluck('other_id')->reject(fn (int $id) => $found->has($id))->values()->all();
    }

    private function row(string $type, int $id, string $name, ?string $detail, array $matched, Collection $reviews, array $more = []): array
    {
        $review = $reviews->get("{$type}:{$id}");

        return [
            'type'       => $type,
            'id'         => $id,
            'name'       => $name,
            'detail'     => $detail,
            'status'     => $more['status'] ?? null,
            'owner'      => $more['owner'] ?? null,
            'matched_on' => $matched,
            'decision'   => $review?->decision,
            'reason'     => $review?->reason,
            'decided_by' => $review?->decider?->name,
            'decided_at' => $review?->updated_at?->toIso8601String(),
        ];
    }
}
