<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** The POS Orders page's filters (Doc 18 Phase G1): register, session and cashier, and a receipt number searched for. */
class ListPosOrdersRequest extends FormRequest
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
            'search'      => ['nullable', 'string', 'max:100'],
        ];
    }
}
