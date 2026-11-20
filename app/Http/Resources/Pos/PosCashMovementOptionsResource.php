<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What recording a cash movement starts from (Doc 18 Phase F1), as
 * PosCashMovementService::options() gives it: the drawer's account, the
 * accounts cash moves to or from - each its kind, which a safe drop and a
 * bank deposit choose by - the cash the drawer should hold, and the limit.
 */
class PosCashMovementOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'drawer'   => $this->resource['drawer'] ? ['id' => $this->resource['drawer']->id, 'name' => $this->resource['drawer']->name] : null,
            'accounts' => $this->resource['accounts']->map(fn ($account) => ['id' => $account->id, 'name' => $account->name, 'type' => $account->type])->values()->all(),
            'currency' => $this->resource['currency'],
            'expected' => $this->resource['expected'],
            'limit'    => $this->resource['limit'],
            // Phase F2: the expense categories a paid out takes - none while it cannot be paid out.
            'categories' => $this->resource['categories']?->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values()->all(),
        ];
    }
}
