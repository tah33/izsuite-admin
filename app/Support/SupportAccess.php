<?php

namespace App\Support;

use App\Models\User\SupportSession;
use Illuminate\Http\Request;

/**
 * What a support session may do in the app (Doc 15 section 15.22), and the
 * session a request comes on, if it does - set by SupportSessionGuard.
 */
class SupportAccess
{
    /** Where SupportSessionGuard leaves the session, on the request's attributes. */
    public const ATTRIBUTE = 'support_session';

    /** The start of every support session's token name; the session's id follows. */
    public const TOKEN_PREFIX = 'izsuite-support:';

    /**
     * Closed to every session: the owner's own account, password and bell,
     * invitations, and - any route whose name holds ".export" - data leaving
     * the business.
     */
    public const CLOSED = [
        'api.profile.*', 'api.notifications.*', 'api.invitations.*',
        'api.auth.password.*', 'api.auth.logout-all', 'api.auth.email.*',
    ];

    /**
     * Never changed by support, edit access or not: the business itself, its
     * subscription and billing, its ownership, and the support cases, which
     * staff answer in Admin > Tickets.
     */
    public const NEVER_CHANGED = [
        'api.workspaces.*', 'api.subscription.*', 'api.members.transfer', 'api.support-cases.*',
    ];

    /** What a session never sees in full, whatever the owner may: payment accounts' full details. */
    public const WITHHELD = ['payment_accounts.details'];

    /** The sensitive areas whose first opening in a session the business's Activity Log records. */
    public const AREAS = [
        'Settings'         => ['api.business-settings.*', 'api.locations.*'],
        'Payment Accounts' => ['api.payment-accounts.*'],
        'Users'            => ['api.members.*', 'api.roles.*'],
    ];

    public static function current(?Request $request = null): ?SupportSession
    {
        $request ??= app()->bound('request') ? app(Request::class) : null;
        $session   = $request?->attributes->get(self::ATTRIBUTE);

        return $session instanceof SupportSession ? $session : null;
    }

    /** Who did something, as a log shows it: "izSuite support (Rafi)" for a session's work, else null. */
    public static function doneBy(?array $properties): ?string
    {
        $admin = $properties['support']['admin'] ?? null;

        return $admin ? "izSuite support ({$admin})" : null;
    }
}
