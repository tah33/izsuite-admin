<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 12 Phase D1: an app's values - amounts, prices, totals - have a
 * permission of their own (section 14). Every role keeps what it sees today:
 * one that may view any of an app's records is given that app's Values. The
 * sidebars, which carry each user's permissions, are built afresh.
 */
return new class extends Migration
{
    /** An app's Values, and the Views that showed them until now. */
    private const VALUES = [
        'sales.values.view'     => ['sales.quotations.view', 'sales.proformas.view', 'sales.invoices.view', 'sales.payments.view', 'sales.credit_notes.view'],
        'purchases.values.view' => ['purchases.purchases.view', 'purchases.payments.view'],
        'expenses.values.view'  => ['expenses.expenses.view', 'expenses.payments.view'],
    ];

    public function up(): void
    {
        DB::table('workspace_roles')->where('full_access', false)->orderBy('id')->each(function (object $role) {
            $held  = json_decode($role->permissions ?? '[]', true) ?: [];
            $given = array_keys(array_filter(self::VALUES, fn (array $views) => array_intersect($views, $held) !== []));
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
            $kept = array_values(array_diff($held, array_keys(self::VALUES)));

            if ($kept !== $held) {
                DB::table('workspace_roles')->where('id', $role->id)->update(['permissions' => json_encode($kept)]);
            }
        });

        DB::table('workspaces')->increment('nav_version');
    }
};
