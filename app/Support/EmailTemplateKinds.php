<?php

namespace App\Support;

/**
 * The platform's e-mails that can be written in the admin (Doc 15 section
 * 15.20 - Phase F2), each by its key: its name, its group, whether it is a
 * mandatory security or billing notice - always sent, whatever anyone in a
 * business chooses - the variables its words may use, each with a sample
 * for previews and tests, and the built-in button's words, for one that
 * carries a link ({link}).
 */
final class EmailTemplateKinds
{
    /** @var array<string, array{0: string, 1: string, 2: bool, 3: array<string, string>, 4: ?string}> */
    public const ALL = [
        'account.verify_email'      => ['Verify your e-mail address', 'Account', true, ['code' => '482913', 'minutes' => '10'], null],
        'account.email_verified'    => ['Your account is active', 'Account', false, ['first_name' => 'Karim', 'email' => 'karim@example.com', 'link' => 'https://app.izsuite.io/login'], 'Sign in'],
        'account.password_reset'    => ['Reset your password', 'Security', true, ['code' => '482913', 'minutes' => '10'], null],
        'account.password_changed'  => ['Your password was changed', 'Security', true, ['first_name' => 'Karim', 'email' => 'karim@example.com', 'changed_at' => '14 Nov 2026, 10:00 UTC'], null],
        'account.invitation'        => ['Invitation to a business', 'Account', false, ['invitee' => 'Nadia', 'business' => 'Karim Stores', 'inviter' => 'Karim Uddin', 'role' => 'Accountant', 'days' => '7', 'link' => 'https://app.izsuite.io/invitations/accept'], 'Accept the invitation'],
        'subscription.trial_ending' => ['Trial ending', 'Billing', true, ['plan' => 'Standard', 'business' => 'Karim Stores', 'days_left' => '3', 'trial_ends_on' => '20 Nov 2026', 'invoice' => 'IZS-INV-00012', 'total' => 'USD 19.50', 'link' => 'https://app.izsuite.io/settings?tab=subscription'], 'See your subscription'],
        'subscription.grace'        => ['Grace-period warning', 'Billing', true, ['plan' => 'Standard', 'business' => 'Karim Stores', 'days_left' => '4', 'last_day' => '13 Dec 2026', 'invoice' => 'IZS-INV-00012', 'total' => 'USD 19.50', 'link' => 'https://app.izsuite.io/settings?tab=subscription'], 'See your subscription'],
        'subscription.ended'        => ['Plan ended', 'Billing', true, ['plan' => 'Standard', 'business' => 'Karim Stores', 'link' => 'https://app.izsuite.io/settings?tab=subscription'], 'See your subscription'],
        'subscription.payment_failed' => ['Failed payment', 'Billing', true, ['plan' => 'Standard', 'business' => 'Karim Stores', 'invoice' => 'IZS-INV-00012', 'total' => 'USD 19.50', 'card' => 'Visa ending 4242', 'link' => 'https://app.izsuite.io/settings?tab=subscription'], 'See your subscription'],
        'subscription.refunded'     => ['Refund made', 'Billing', true, ['business' => 'Karim Stores', 'amount' => 'USD 19.50', 'method' => 'Visa ending 4242', 'invoice' => 'IZS-INV-00012', 'link' => 'https://app.izsuite.io/settings?tab=subscription'], 'See your subscription'],
        'subscription.price_change' => ['Price change', 'Billing', true, ['plan' => 'Standard', 'business' => 'Karim Stores', 'was' => 'USD 19.50', 'now' => 'USD 24.00', 'per' => 'month', 'from' => '1 Jan 2027', 'link' => 'https://app.izsuite.io/settings?tab=subscription'], 'See your subscription'],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    public static function name(string $key): string
    {
        return self::ALL[$key][0];
    }

    /** @return array<string, string> variable => sample */
    public static function variables(string $key): array
    {
        return self::ALL[$key][3];
    }

    /** The built-in button's words - null for an e-mail without a link. */
    public static function button(string $key): ?string
    {
        return self::ALL[$key][4];
    }
}
