<?php

namespace App\Services\Api\Crm;

use App\Models\User\Contact;
use App\Models\User\CrmLead;
use App\Models\User\MarketingConsent;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\MarketingConsentRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marketing consent (Doc 17 section 17.25 - Phase I3), a lead's or a
 * contact's, apart from sales follow-up: its answers, the latest first, the
 * one standing for each purpose and channel marked; an answer recorded -
 * consent given or refused - and a consent given withdrawn. Nothing is
 * deleted, so withdrawing keeps the history. Each is logged on the lead or
 * contact, so its timeline shows it.
 *
 * A converted lead's answers went to its contact (CrmLeadConversion), and a
 * merged contact's to the one it was merged into: they are changed there.
 * An unqualified or archived lead, or an archived contact, still takes an
 * answer - a refusal or a withdrawal holds at once.
 */
class CrmConsentService
{
    use RefusesConflicts;

    /** A record's kind => its column. */
    public const COLUMNS = ['lead' => 'lead_id', 'contact' => 'contact_id'];

    /** How each channel is named in the log. */
    private const BY = ['email' => 'by e-mail', 'sms' => 'by SMS', 'messaging' => 'by messaging apps', 'phone' => 'by phone'];

    public function __construct(
        protected MarketingConsentRepository $consents,
        protected CrmOptionRepository $options,
        protected ContactRepository $contacts,
        protected CrmLeadService $leads,
        protected CrmSettingService $settings,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * $kind's record $id - one $user sees - with its answers, each marked,
     * and the consent purposes switched on that an answer is recorded for.
     *
     * @return array{consents: Collection, purposes: \Illuminate\Support\Collection}
     */
    public function index(Workspace $workspace, User $user, string $kind, int $id): array
    {
        $record = $this->subject($workspace, $user, $kind, $id);

        // The lists start here if Settings → CRM was never opened.
        $this->settings->start($workspace);

        return [
            'consents' => $this->marked($workspace, $this->consents->forRecord($workspace, self::COLUMNS[$kind], $record->id)),
            'purposes' => $this->options->getFor($workspace)->where('kind', 'consent_purpose')->where('is_active', true)->values(),
        ];
    }

    /**
     * An answer for $kind's record $id; $data is validated (see
     * RecordMarketingConsentRequest): its purpose - one switched on - and
     * channel, given or refused, where from, when (recorded_on and
     * recorded_time, the business's - now, left out), a consent given's
     * expiry and the evidence. What index() answers after it.
     */
    public function record(Workspace $workspace, User $user, string $kind, int $id, array $data): array
    {
        $record  = $this->changeable($workspace, $user, $kind, $id);
        $purpose = $this->options->findFor($workspace, (int) $data['purpose_id']);

        if (! $purpose || $purpose->kind !== 'consent_purpose' || ! $purpose->is_active) {
            throw ValidationException::withMessages(['purpose_id' => 'Choose one of the consent purposes switched on in Settings → CRM.']);
        }

        $at    = $this->moment($workspace, $data['recorded_on'] ?? null, $data['recorded_time'] ?? null, 'recorded_on');
        $given = $data['status'] === MarketingConsent::GIVEN;

        if ($given && ! empty($data['expires_on']) && $data['expires_on'] <= $at->setTimezone($this->calendar->timezone($workspace))->toDateString()) {
            throw ValidationException::withMessages(['expires_on' => 'A consent expires after the day it was given.']);
        }

        $consent = $this->consents->create($workspace, [
            self::COLUMNS[$kind] => $record->id,
            'purpose_id'         => $purpose->id,
            'channel'            => $data['channel'],
            'status'             => $data['status'],
            'source'             => $data['source'],
            'recorded_at'        => $at,
            'recorded_by'        => $user->id,
            'expires_on'         => $given ? ($data['expires_on'] ?? null) : null,
            'evidence'           => $data['evidence'] ?? null,
        ]);

        ActivityLogService::record('updated', 'Recorded marketing consent '.($given ? 'given' : 'refused').' for '.$this->describe($consent), $record, [
            'source' => 'api', 'consent_id' => $consent->id,
        ]);

        return $this->index($workspace, $user, $kind, $id);
    }

    /**
     * The consent given $consentId of $kind's record $id withdrawn - only the
     * one standing for its purpose and channel - when withdrawn_on and
     * withdrawn_time say (now, left out), with a note on how. What index()
     * answers after it.
     */
    public function withdraw(Workspace $workspace, User $user, string $kind, int $id, int $consentId, array $data): array
    {
        $record  = $this->changeable($workspace, $user, $kind, $id);
        $column  = self::COLUMNS[$kind];
        $consent = $this->consents->findForRecord($workspace, $column, $record->id, $consentId) ?? abort(404, 'There is no such consent.');
        $at      = $this->moment($workspace, $data['withdrawn_on'] ?? null, $data['withdrawn_time'] ?? null, 'withdrawn_on');

        DB::transaction(function () use ($workspace, $user, $record, $column, $consent, $at, $data) {
            $standing = $this->consents->standing($workspace, $column, $record->id, $consent->purpose_id, $consent->channel);

            if ($standing?->id !== $consent->id || $standing->status !== MarketingConsent::GIVEN) {
                $this->refuse('Only a consent given that still stands for its purpose and channel is withdrawn.');
            }

            if ($at->lt($standing->recorded_at)) {
                throw ValidationException::withMessages(['withdrawn_on' => 'A consent is withdrawn after it was given.']);
            }

            $this->consents->update($standing, ['status' => MarketingConsent::WITHDRAWN, 'withdrawn_at' => $at, 'withdrawn_by' => $user->id, 'withdrawal_note' => $data['note'] ?? null]);

            ActivityLogService::record('updated', 'Withdrew marketing consent for '.$this->describe($standing), $record, ['source' => 'api', 'consent_id' => $standing->id]);
        });

        return $this->index($workspace, $user, $kind, $id);
    }

    /** $kind's record $id that $user sees: a lead as LeadVisibility gives it, any contact of the business. */
    private function subject(Workspace $workspace, User $user, string $kind, int $id): CrmLead|Contact
    {
        return $kind === 'lead'
            ? $this->leads->find($workspace, $user, $id)
            : ($this->contacts->findFor($workspace, $id) ?? abort(404, 'Contact not found.'));
    }

    /** subject(), while its answers are its own to change: not a converted lead's, nor a merged contact's. */
    private function changeable(Workspace $workspace, User $user, string $kind, int $id): CrmLead|Contact
    {
        $record = $this->subject($workspace, $user, $kind, $id);

        if ($record instanceof CrmLead && $record->status === CrmLead::CONVERTED) {
            $this->refuse('This lead was converted: its marketing consent went with it to its contact, so record it there.');
        }

        if ($record instanceof Contact && $record->isMerged()) {
            $this->refuse('This contact was merged into another: record its marketing consent there.');
        }

        return $record;
    }

    /** $on at $time - midnight, left out - in the business's time zone; now when $on is left out. Never later than now. */
    private function moment(Workspace $workspace, ?string $on, ?string $time, string $field): CarbonImmutable
    {
        if ($on === null) {
            return CarbonImmutable::now();
        }

        $at = CarbonImmutable::createFromFormat('Y-m-d H:i', $on.' '.($time ?: '00:00'), $this->calendar->timezone($workspace))->utc();

        if ($at->isFuture()) {
            throw ValidationException::withMessages([$field => 'That moment has not come yet.']);
        }

        return $at;
    }

    /**
     * $consents - the latest first - each with its shown_status (a consent
     * given past its expiry day reads expired) and whether it is the one
     * standing for its purpose and channel: the latest.
     */
    private function marked(Workspace $workspace, Collection $consents): Collection
    {
        $today = $this->calendar->today($workspace)->toDateString();
        $seen  = [];

        foreach ($consents as $consent) {
            $key = $consent->purpose_id.'|'.$consent->channel;

            $consent->setAttribute('standing', ! isset($seen[$key]));
            $consent->setAttribute('shown_status', $consent->status === MarketingConsent::GIVEN && $consent->expires_on && $consent->expires_on->toDateString() < $today ? 'expired' : $consent->status);
            $seen[$key] = true;
        }

        return $consents;
    }

    /** What the log calls its purpose and channel: '"Newsletters" by e-mail'. */
    private function describe(MarketingConsent $consent): string
    {
        return '"'.$consent->purpose?->name.'" '.self::BY[$consent->channel];
    }
}
