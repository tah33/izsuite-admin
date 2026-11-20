<?php

namespace App\Console\Commands;

use App\Services\Api\Pos\PosHoldService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Expires each POS order still held past Settings → Point of Sale's hours
 * (Doc 18 section 18.22 - Phase D1): it could not be resumed since its
 * time came already; this records it as expired, in the business's Activity
 * Log. Scheduled hourly.
 */
class ExpireHeldPosOrders extends Command
{
    protected $signature   = 'pos:expire-held-orders';

    protected $description = 'Expire the POS orders held past their time';

    public function handle(PosHoldService $holds): int
    {
        $expired = $holds->expire();

        $this->info('Expired '.$expired.' held '.Str::plural('order', $expired).'.');

        return self::SUCCESS;
    }
}
