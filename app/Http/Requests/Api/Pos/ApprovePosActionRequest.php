<?php

namespace App\Http\Requests\Api\Pos;

use App\Services\Api\Pos\PosApprovals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovePosActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A manager's approval at the cashier's register (Doc 18 Phase I2): of what, by whom, with their PIN. */
    public function rules(): array
    {
        return [
            'action'      => ['required', Rule::in(array_keys(PosApprovals::ACTIONS))],
            'approver_id' => ['required', 'integer'],
            'pin'         => ['required', 'string', 'max:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'approver_id.required' => 'Choose who approves it.',
            'pin.required'         => 'Their PIN approves it.',
        ];
    }
}
