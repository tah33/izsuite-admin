<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 12 Phase D2: items' purchase prices, payment accounts' balances and
 * full details, and tax information have permissions of their own (section
 * 14). Every role keeps what it sees today: one that may view items, payment
 * accounts, or anything that shows tax details - contacts, documents,
 * reports, Settings - is given the matching permission, and one that may read
 * the Activity Log, which shows them all, every one. The sidebars, which
 * carry each user's permissions, are built afresh.
 */
return new class extends Migration
{
    /** Each new permission, and the Views that showed what it covers until now. */
    private const GIVEN = [
        'items.costs'               => ['items.view', 'settings.audit'],
        'payment_accounts.balances' => ['payment_accounts.view', 'settings.audit'],
        'payment_accounts.details'  => ['payment_accounts.view', 'settings.audit'],
        'taxes.view'                => [
            'contacts.view', 'reports.view', 'settings.view', 'settings.audit', 'documents.view',
            'sales.quotations.view', 'sales.proformas.view', 'sales.invoices.view', 'sales.credit_notes.view',
            'purchases.purchases.view', 'expenses.expenses.view',
        ],
    ];

    public function up(): void
    {
        DB::table('workspace_roles')->where('full_access', false)->orderBy('id')->each(function (object $role) {
            $held  = json_decode($role->permissions ?? '[]', true) ?: [];
            $given = array_keys(array_filter(self::GIVEN, fn (array $views) => array_intersect($views, $held) !== []));
            $now   = array_values(array_unique([...$held, ...$given]));

            if ($now !== $held) {
                DB::table('workspace_roles')->where('id', $role->id)->update(['permissions' => json_encode($now)]);
            }
        });

        DB::table('workspaces')->increment('nav_version');
    }

    public function down(): void
    {
        DB::table('workspace_roles')->orderBy('id')->each(function (object $role) {
            $held = json_decode($role->permissions ?? '[]', true) ?: [];
            $kept = array_values(array_diff($held, array_keys(self::GIVEN)));

            if ($kept !== $held) {
                DB::table('workspace_roles')->where('id', $role->id)->update(['permissions' => json_encode($kept)]);
            }
        });

        DB::table('workspaces')->increment('nav_version');
    }
};
