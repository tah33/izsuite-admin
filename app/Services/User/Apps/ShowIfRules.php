<?php

namespace App\Services\User\Apps;

use App\Models\User\Workspace;
use App\Repositories\User\CrmSettingRepository;
use App\Repositories\User\PaymentAccountRepository;

/**
 * The conditions a menu item can be shown on ("Show if" in the Menu Builder).
 *
 * Rules live in code because each one checks real data; the admin picks one by
 * its key and never types an expression. An unknown key hides the item.
 */
class ShowIfRules
{
    public function __construct(
        private PaymentAccountRepository $paymentAccounts,
        private CrmSettingRepository $crmSettings,
    ) {}

    /** key => label, for the Menu Builder's dropdown. */
    public function options(): array
    {
        return [
            'has_payment_account' => 'Has a payment account',
            'crm_uses_leads'      => 'CRM uses leads',
        ];
    }

    public function passes(string $key, ?Workspace $workspace): bool
    {
        return match ($key) {
            // Payment Accounts appears once the first one is made (Doc 01
            // section 6.2). The first one made and the last one deleted bump
            // the business's nav_version (see PaymentAccount), so the cached
            // sidebar follows.
            'has_payment_account' => $workspace !== null && $this->paymentAccounts->existsFor($workspace),
            // Leads, unless the business uses opportunities only (Doc 17 section 17.5). Saving the mode bumps nav_version.
            'crm_uses_leads'      => $workspace !== null && $this->crmSettings->forWorkspace($workspace)->usesLeads(),
            default               => false,
        };
    }
}
