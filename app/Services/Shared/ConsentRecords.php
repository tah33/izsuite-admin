<?php

namespace App\Services\Shared;

use App\Models\Admin\ConsentRecord;
use App\Models\User\User;
use App\Repositories\Admin\ConsentRecordRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Consent records (Doc 15 section 15.24 - Phase G3): the terms accepted at
 * sign-up, and izSuite's product news by e-mail given or withdrawn - at
 * sign-up, then in the profile - each recorded once with when, from which
 * address and browser, and never changed. The account keeps its marketing
 * choice as it stands (users.marketing_consent); the records are its
 * history, which an admin sees on the account's page.
 */
class ConsentRecords
{
    public function __construct(
        protected ConsentRecordRepository $records,
        protected UserRepository $users,
    ) {}

    /** $user accepted the terms, from $source. */
    public function acceptedTerms(User $user, string $source): ConsentRecord
    {
        return $this->record($user, ConsentRecord::TERMS, true, $source);
    }

    /**
     * $user's consent to product news by e-mail, from $source: kept, and
     * recorded when it changes. A first "no" at sign-up is the default and
     * not recorded.
     */
    public function setMarketing(User $user, bool $given, string $source): void
    {
        if ((bool) $user->marketing_consent === $given) {
            return;
        }

        $this->users->forceUpdate($user, ['marketing_consent' => $given]);
        $this->record($user, ConsentRecord::MARKETING, $given, $source);
    }

    private function record(User $user, string $kind, bool $given, string $source): ConsentRecord
    {
        $request = app()->bound('request') ? app(Request::class) : null;

        return $this->records->create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'kind'       => $kind,
            'given'      => $given,
            'source'     => $source,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
        ]);
    }
}
