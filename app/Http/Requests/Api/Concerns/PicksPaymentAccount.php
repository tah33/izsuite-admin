<?php

namespace App\Http\Requests\Api\Concerns;

use App\Services\Shared\WorkspaceAccess;
use Closure;
use Illuminate\Database\Query\Builder;

/**
 * Money moves only through an account the user may use (Doc 12 section 21):
 * the whole business's, or one of their locations'. For a Rule::exists on
 * payment_accounts.
 */
trait PicksPaymentAccount
{
    protected function usableAccount(): Closure
    {
        $locations = app(WorkspaceAccess::class)->locations();

        return fn (Builder $query) => $locations === null
            ? $query
            : $query->where(fn (Builder $query) => $query->whereNull('location_id')->orWhereIn('location_id', $locations));
    }
}
