<?php

namespace App\Http\Requests\Api\Plans;

use Illuminate\Foundation\Http\FormRequest;

class ListPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Query strings always arrive as text, and Laravel's boolean rule rejects
     * the literal "true"/"false" that an API consumer naturally writes. Coerce
     * the recognised forms up front; anything unrecognised is left untouched so
     * the boolean rule still reports it.
     */
    protected function prepareForValidation(): void
    {
        // filter_var() reads an empty string as false, so bail out on a blank
        // value and let it fall through as "no filter" instead.
        if (! $this->filled('featured')) {
            return;
        }

        $normalized = filter_var($this->input('featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized !== null) {
            $this->merge(['featured' => $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'search'       => ['nullable', 'string', 'max:255'],
            'billing_type' => ['nullable', 'in:monthly,yearly'],
            'plan_for'     => ['nullable', 'string', 'max:50'],
            'featured'     => ['nullable', 'boolean'],
            'per_page'     => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ];
    }
}
