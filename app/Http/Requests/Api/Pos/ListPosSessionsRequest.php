<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Sessions page's filters (Doc 18 Phase B1): status and register. */
class ListPosSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'      => ['nullable', Rule::in(PosSession::STATUSES)],
            'register_id' => ['nullable', 'integer'],
        ];
    }
}
