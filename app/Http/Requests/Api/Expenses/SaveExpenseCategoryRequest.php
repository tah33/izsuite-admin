<?php

namespace App\Http\Requests\Api\Expenses;

use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An expense category, added or changed (Doc 10 section 7): a name used
     * once in the business, an optional description, the tax its lines start
     * with - one of the business's expense taxes - and whether it is offered
     * to new expenses. Left out, it is.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'description' => ['nullable', 'string', 'max:500'],
            'tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('workspace_id', $workspace?->id)->where('for_expenses', true)],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    /** A tax switched off is not taken for a category - unless it is the one it already starts with. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $workspace = $this->attributes->get('workspace');

            if ($validator->errors()->isNotEmpty() || ! $this->input('tax_rate_id')) {
                return;
            }

            $rate = app(TaxRateRepository::class)->findFor($workspace, (int) $this->input('tax_rate_id'));
            $kept = $this->route('id') ? app(ExpenseCategoryRepository::class)->findFor($workspace, (int) $this->route('id'))?->tax_rate_id : null;

            if ($rate && ! $rate->is_active && $rate->id !== $kept) {
                $validator->errors()->add('tax_rate_id', "{$rate->name} is inactive.");
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.unique'        => 'You already have a category of that name.',
            'tax_rate_id.exists' => 'Choose one of your expense taxes.',
        ];
    }
}
