<?php

namespace App\Services\Api\Sales;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A step a sales document's status does not allow - deleting an issued
 * invoice, accepting an expired quotation - answered with a 409 and a message
 * the page shows as it is.
 */
trait RefusesConflicts
{
    protected function refuse(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 409));
    }
}
