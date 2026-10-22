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
            // section 6.2). The first one made and the last one deleted bump
            // the business's nav_version (see PaymentAccount), so the cached
            // sidebar follows.
            'has_payment_account' => (bool) $workspace?->paymentAccounts()->exists(),
            default               => false,
        };
    }
}
