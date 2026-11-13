<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Doc 15 Phase C1: Admin > Subscriptions (section 15.13) in place of the
 * earlier product's Plans & Pricing and Subscription History, whose screens
 * are gone - their tables stay until you decide.
 *
 * Every role loses those screens' permissions. The starting roles gain the
 * new ones of their areas: Billing every subscription action, Support the
 * list and resending an invoice, Read-Only the list.
 */
return new class extends Migration
{
    /** The earlier product's screens, gone with this phase. */
    private const GONE = [
        'admin.plans.index', 'admin.plans.create', 'admin.plans.store', 'admin.plans.edit', 'admin.plans.update', 'admin.plans.destroy',
        'admin.subscriptions.create', 'admin.subscriptions.store', 'admin.subscriptions.invoice', 'admin.subscriptions.pause', 'admin.subscriptions.resume',
    ];

    /** slug => the routes it gains */
    private const GAINS = [
        'billing-administrator'   => [
            'admin.subscriptions.index', 'admin.subscriptions.show', 'admin.subscriptions.plan', 'admin.subscriptions.trial', 'admin.subscriptions.grace',
            'admin.subscriptions.cancel', 'admin.subscriptions.undo', 'admin.subscriptions.resend', 'admin.subscriptions.retry',
        ],
        'support-administrator'   => ['admin.subscriptions.index', 'admin.subscriptions.show', 'admin.subscriptions.resend'],
        'read-only-administrator' => ['admin.subscriptions.index', 'admin.subscriptions.show'],
    ];

    public function up(): void
    {
        foreach (Role::all() as $role) {
            $held        = $role->permissions ?? [];
            $permissions = array_values(array_unique([...array_diff($held, self::GONE), ...(self::GAINS[$role->slug] ?? [])]));

            if ($permissions !== $held) {
                $role->update(['permissions' => $permissions]);
            }
        }
    }

    public function down(): void
    {
        // The earlier product's screens are gone; nothing to give back.
    }
};
