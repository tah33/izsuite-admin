<?php

namespace App\Console\Commands;

use App\Services\Api\Ecommerce\StorefrontCartService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Marks abandoned each storefront cart whose customer is known, left unused
 * past Settings → eCommerce's hours (Doc 19 section 19.32 - Phase H3a): the
 * business's Abandoned Carts page lists it. It stays its visitor's, Active
 * again once used. Scheduled hourly.
 */
class AbandonEcommerceCarts extends Command
{
    protected $signature   = 'ecommerce:abandon-carts';

    protected $description = 'Mark abandoned the storefront carts whose customer is known, unused past their business\'s hours';

    public function handle(StorefrontCartService $carts): int
    {
        $abandoned = $carts->abandon();

        $this->info('Abandoned '.$abandoned.' '.Str::plural('cart', $abandoned).'.');

        return self::SUCCESS;
    }
}
