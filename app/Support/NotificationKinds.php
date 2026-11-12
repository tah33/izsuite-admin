<?php

namespace App\Support;

/**
 * The kinds of notification people choose about (Doc 14 section 15 - Phase
 * B2), each with its words, whether it is always on, and whether it goes by
 * e-mail unless turned off. A trigger's kind is the part before its dot:
 * "apps.activated" is "apps".
 *
 * Subscription and billing is always on (Doc 13 section 19): its e-mails go
 * to the billing e-mail and the owner on their own. Security and ownership
 * notices join it, always on, when they are sent.
 */
final class NotificationKinds
{
    /** key => [label, description, always on, by e-mail unless turned off] */
    public const KINDS = [
        'subscription' => ['Subscription and billing', 'Trials, renewals, payments, grace and plan changes. Always on; their e-mails go to the billing e-mail and the owner.', true, false],
        'usage'        => ['Usage limits', "Users, active locations or storage nearly at, at or past the plan's limits.", false, true],
        'apps'         => ['Apps', 'Apps turned on or off.', false, false],
        'members'      => ['Users', 'Someone joining the business from an invitation.', false, true],
        'comments'     => ['Comments and mentions', 'Someone mentioning you in a comment on a sales document, purchase or expense.', false, true],
    ];

    public static function of(string $trigger): string
    {
        return explode('.', $trigger, 2)[0];
    }

    public static function locked(string $kind): bool
    {
        return self::KINDS[$kind][2] ?? false;
    }

    /**
     * Every kind as it stands for $chosen - choices kept by kind and channel,
     * unset meaning the default: a business's, the kind's; a user's ($user),
     * on - they receive what the business sends until they turn it off.
     *
     * @return array<string, array{in_app: bool, email: bool}>
     */
    public static function resolve(?array $chosen, bool $user = false): array
    {
        $resolved = [];

        foreach (self::KINDS as $key => [, , $locked, $email]) {
            $resolved[$key] = [
                'in_app' => $locked || (bool) ($chosen[$key]['in_app'] ?? true),
                'email'  => ! $locked && (bool) ($chosen[$key]['email'] ?? ($user || $email)),
            ];
        }

        return $resolved;
    }
}
