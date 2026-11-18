<?php

namespace App\Support;

/**
 * A business's status in izSuite (Doc 15 section 15.6), apart from its
 * subscription's: a business on the free plan is Active, and one in grace
 * stays Active. Only the admin panel changes it, and nothing it does deletes
 * anything.
 *
 * - Restricted and Under Review: members read, but change nothing.
 * - Suspended: nobody opens it; its owner sees why.
 * - Closure Scheduled: it works until its closing day, then is Closed.
 * - Closed: nobody opens it. Its records are kept.
 */
final class BusinessStatus
{
    public const ACTIVE            = 'active';

    public const RESTRICTED        = 'restricted';

    public const UNDER_REVIEW      = 'under_review';

    public const SUSPENDED         = 'suspended';

    public const CLOSURE_SCHEDULED = 'closure_scheduled';

    public const CLOSED            = 'closed';

    public const LABELS = [
        self::ACTIVE            => 'Active',
        self::RESTRICTED        => 'Restricted',
        self::UNDER_REVIEW      => 'Under Review',
        self::SUSPENDED         => 'Suspended',
        self::CLOSURE_SCHEDULED => 'Closure Scheduled',
        self::CLOSED            => 'Closed',
    ];

    /** Members may read, but not change anything. */
    public const READ_ONLY = [self::RESTRICTED, self::UNDER_REVIEW];

    /** Nobody opens it. */
    public const SHUT = [self::SUSPENDED, self::CLOSED];

    /** What an admin may set it to. Closed comes only on a scheduled closure's day. */
    public const SETTABLE = [self::ACTIVE, self::RESTRICTED, self::UNDER_REVIEW, self::SUSPENDED, self::CLOSURE_SCHEDULED];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function isReadOnly(string $status): bool
    {
        return in_array($status, self::READ_ONLY, true);
    }

    public static function isShut(string $status): bool
    {
        return in_array($status, self::SHUT, true);
    }
}
