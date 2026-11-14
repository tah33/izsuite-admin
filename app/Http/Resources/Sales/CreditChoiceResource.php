<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One choice of the Apply Credit dialog (Doc 16 Phase D1): an advance or credit
 * note an invoice can take, or an invoice an advance or credit note can go to,
 * with what it has available - credit left, or what is still owed.
 */
class CreditChoiceResource extends JsonResource
{
    use HidesValues;

    public function toArray(Request $request): array
    {
        return $this->hideValues('sales', [
            'kind'      => $this->resource['kind'],
            'id'        => $this->resource['id'],
            'number'    => $this->resource['number'],
            'date'      => $this->resource['date'],
            'currency'  => $this->resource['currency'],
            'available' => $this->resource['available'],
        ], ['available']);
    }
}
