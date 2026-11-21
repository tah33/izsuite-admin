<?php

namespace App\Console\Commands;

use App\Services\Api\Ecommerce\StorefrontCartService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Expires each storefront cart left unused past Settings → eCommerce's days
 * (Doc 19 section 19.14 - Phase D3): it could not be used since its time came
 * already; this records it as expired. Scheduled hourly.
 */
class ExpireEcommerceCarts extends Command
{
    protected $signature   = 'ecommerce:expire-carts';

    protected $description = 'Expire the storefront carts unused past their days';

    public function handle(StorefrontCartService $carts): int
    {
        $expired = $carts->expire();

        $this->info('Expired '.$expired.' '.Str::plural('cart', $expired).'.');

        return self::SUCCESS;
    }
}
