<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer a discount may be for (Doc 19 Phase H2b), as its form offers
 * them: their id and name, and their e-mail to tell two of a name apart.
 *
 * @property Contact $resource
 */
class EcommerceDiscountContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'email' => $this->resource->email];
    }
}
