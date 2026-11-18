<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\SalesReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A return's step (Doc 16 Phase E): the number typed on submitting, the day
 * the goods came back, the resolution on completing, or why it was rejected
 * or cancelled. Each step reads what it needs.
 */
class ReturnStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $completing = $this->route()?->getName() === 'api.sales.returns.complete';

        return [
            'number'      => ['nullable', 'string', 'max:50'],
            'received_on' => ['nullable', 'date_format:Y-m-d'],
            'resolution'  => [$completing ? 'required' : 'nullable', Rule::in(SalesReturn::RESOLUTIONS)],
            'reason'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolution.required' => 'Choose what was done about it.',
        ];
    }
}