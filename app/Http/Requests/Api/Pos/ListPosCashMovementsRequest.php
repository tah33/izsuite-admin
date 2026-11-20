<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosCashMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Cash Movements page's filters (Doc 18 Phase G1): register, session and who recorded it, its kind and status. */
class ListPosCashMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'register_id' => ['nullable', 'integer'],
            'session_id'  => ['nullable', 'integer'],
            'cashier_id'  => ['nullable', 'integer'],
            'type'        => ['nullable', Rule::in(PosCashMovement::TYPES)],
            'status'      => ['nullable', Rule::in([PosCashMovement::COMPLETED, PosCashMovement::PENDING, PosCashMovement::DECLINED])],
        ];
    }
}
