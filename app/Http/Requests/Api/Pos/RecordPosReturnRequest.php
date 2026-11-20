<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A POS return (Doc 18 Phase E1): the sale, each of its lines brought back -
 * how much, and in what condition - why, notes, and how it is refunded: in
 * cash, by the sale's own method, or left as the customer's credit.
 */
class RecordPosReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...QuotePosReturnRequest::lines(),
            'lines.*.condition' => ['required', Rule::in(PosReturn::CONDITIONS)],
            'reason'            => ['required', 'string', 'max:500'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'refund'            => ['required', Rule::in(PosReturn::REFUNDS)],
            // Phase E2: another method, and why.
            'method_id'         => ['nullable', 'integer'],
            'refund_reason'     => ['nullable', 'string', 'max:500'],
            // Doc 18 Phase I2: a manager's approval at the register, for another method.
            'approvals'         => ['nullable', 'array'],
            'approvals.refund'  => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            ...QuotePosReturnRequest::lineMessages(),
            'reason.required' => 'Say why it is brought back.',
        ];
    }
}
