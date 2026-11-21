<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings → eCommerce's payment methods (Doc 19 Phase D2), in their order, and the accounts a method may take money into. */
class EcommercePaymentMethodsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'methods'  => EcommercePaymentMethodResource::collection($this->resource['methods'])->resolve($request),
            'accounts' => $this->resource['accounts']->map(fn (PaymentAccount $account) => ['id' => $account->id, 'name' => $account->name, 'currency' => $account->currency])->values(),
        ];
    }
}
