<?php

namespace App\Services\User\Apps;

use App\Models\User\Workspace;

/**
 * The conditions a menu item can be shown on ("Show if" in the Menu Builder).
 *
 * Rules live in code because each one checks real data; the admin picks one by
 * its key and never types an expression. An unknown key hides the item.
 */
class ShowIfRules
{
    /** key => label, for the Menu Builder's dropdown. */
    public function options(): array
    {
        return [
            'has_payment_account' => 'Has a payment account',
        ];
    }

    public function passes(string $key, ?Workspace $workspace): bool
    {
        return match ($key) {
            // Payment Accounts appears once the first one is made (Doc 01
            // section 6.2). They are not built yet, so no business has one and
            // the item stays hidden; when they are, this becomes
            // (bool) $workspace?->paymentAccounts()->exists(), and creating the
            // first one must bump the business's nav_version.
            'has_payment_account' => false,
            default               => false,
        };
    }
}
