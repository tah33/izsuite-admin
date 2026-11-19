<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmLead;
use App\Models\User\CrmOption;
use App\Models\User\MarketingConsent;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** A business's marketing consent answers (Doc 17 Phase I3), a contact's or a lead's. */
class MarketingConsentRepository
{
    /** What a row shows: its purpose, and who recorded and withdrew it. */
    private const SHOWN_WITH = ['purpose:id,name', 'recorder:id,first_name,last_name', 'withdrawer:id,first_name,last_name'];

    /**
     * The answers of $workspace's record whose $column (contact_id or
     * lead_id) is $id, the latest first.
     */
    public function forRecord(Workspace $workspace, string $column, int $id): Collection
    {
        return MarketingConsent::query()
            ->where('workspace_id', $workspace->id)
            ->where($column, $id)
            ->with(self::SHOWN_WITH)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get();
    }

    /** One of the answers of the record whose $column is $id. */
    public function findForRecord(Workspace $workspace, string $column, int $id, int $consentId): ?MarketingConsent
    {
        return MarketingConsent::query()->where('workspace_id', $workspace->id)->where($column, $id)->find($consentId);
    }

    /**
     * The answer standing for $purposeId on $channel of the record whose
     * $column is $id - the latest - locked until it is saved.
     */
    public function standing(Workspace $workspace, string $column, int $id, int $purposeId, string $channel): ?MarketingConsent
    {
        return MarketingConsent::query()
            ->where('workspace_id', $workspace->id)
            ->where($column, $id)
            ->where('purpose_id', $purposeId)
            ->where('channel', $channel)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): MarketingConsent
    {
        return $workspace->marketingConsents()->create($data)->load(self::SHOWN_WITH);
    }

    public function update(MarketingConsent $consent, array $data): MarketingConsent
    {
        $consent->update($data);

        return $consent->load(self::SHOWN_WITH);
    }

    /** $lead's answers are its contact's now as well (Phase C1's conversion): each names both. */
    public function carryToContact(CrmLead $lead, int $contactId): void
    {
        MarketingConsent::query()->where('lead_id', $lead->id)->update(['contact_id' => $contactId]);
    }

    /** Whether any answer is about $purpose: it is then switched off, not deleted. */
    public function usesPurpose(CrmOption $purpose): bool
    {
        return MarketingConsent::query()->where('purpose_id', $purpose->id)->exists();
    }

    /** Whether $contact gave any answer: it is then deactivated, not deleted, as its history stays. */
    public function usesContact(Contact $contact): bool
    {
        return MarketingConsent::query()->where('contact_id', $contact->id)->exists();
    }
}
