<?php

namespace App\Repositories\Billing;

use Illuminate\Support\Facades\DB;

/**
 * izSuite's own counters for its subscription invoices and receipts (Doc 13
 * Phase C) - one per kind, for every business together, so numbers never
 * repeat.
 */
class BillingSequenceRepository
{
    /** The counter's next value; the row is locked until the caller's transaction ends. */
    public function next(string $name): int
    {
        $now  = now();

        DB::table('billing_sequences')->insertOrIgnore(['name' => $name, 'last_value' => 0, 'created_at' => $now, 'updated_at' => $now]);

        $row  = DB::table('billing_sequences')->where('name', $name)->lockForUpdate()->first();
        $next = (int) $row->last_value + 1;

        DB::table('billing_sequences')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => $now]);

        return $next;
    }
}
