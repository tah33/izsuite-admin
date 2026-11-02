<?php

namespace App\Http\Requests\Api\Concerns;

use App\Support\Decimal;

/**
 * An exchange rate is kept to the business's rate precision (Doc 11 section
 * 7): one typed with more decimals is rounded, half up, before it is
 * validated - so a rate saved before the precision was lowered still goes
 * through, as the precision now says. Anything but a plain decimal is left
 * for the rules to refuse.
 */
trait RoundsExchangeRates
{
    protected function roundRate(string $field): void
    {
        $workspace = $this->attributes->get('workspace');
        $value     = is_numeric($this->input($field)) ? (string) $this->input($field) : null;

        if ($workspace && $value !== null && preg_match('/^\d+(\.\d+)?$/', $value)) {
            $this->merge([$field => Decimal::round($value, $workspace->rate_precision ?? 6)]);
        }
    }
}
