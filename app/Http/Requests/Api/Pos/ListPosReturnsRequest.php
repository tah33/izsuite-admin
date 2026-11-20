<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Returns and Refunds page's filters (Doc 18 Phase G1): register, session and cashier, how it was paid back, its refund's status, and a number searched for. */
class ListPosReturnsRequest extends FormRequest
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
            'refund'      => ['nullable', Rule::in(PosReturn::REFUNDS)],
            'status'      => ['nullable', Rule::in([PosReturn::COMPLETED, PosReturn::PENDING, PosReturn::DECLINED])],
            'search'      => ['nullable', 'string', 'max:100'],
        ];
    }
}
