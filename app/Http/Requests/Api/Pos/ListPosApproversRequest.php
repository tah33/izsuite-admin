<?php

namespace App\Http\Requests\Api\Pos;

use App\Services\Api\Pos\PosApprovals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPosApproversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Who may approve - what (Doc 18 Phase I2). */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(array_keys(PosApprovals::ACTIONS))],
        ];
    }
}
