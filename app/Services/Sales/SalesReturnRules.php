<?php

namespace App\Services\Sales;

use App\Models\User\SalesReturn;

/**
 * What a customer return can do in each status (Doc 16 Phase E): a draft is
 * changed, submitted or deleted; a submitted one is still changed, then
 * authorised, rejected or cancelled; an authorised one is received or
 * cancelled; a received one is completed. Rejected, completed and cancelled
 * ones are final.
 */
class SalesReturnRules
{
    public function actions(SalesReturn $return): array
    {
        return match ($return->status) {
            SalesReturn::DRAFT      => ['edit', 'submit', 'delete'],
            SalesReturn::SUBMITTED  => ['edit', 'authorise', 'reject', 'cancel'],
            SalesReturn::AUTHORISED => ['receive', 'cancel'],
            SalesReturn::RECEIVED   => ['complete'],
            default                 => [],
        };
    }

    public function allows(SalesReturn $return, string $action): bool
    {
        return in_array($action, $this->actions($return), true);
    }
}
