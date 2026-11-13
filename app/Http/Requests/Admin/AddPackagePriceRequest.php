<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\WorkspaceSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A plan's new price (Doc 15 Phase C3a): its currency - one the admin keeps - cycle, amount, and the day it starts. */
class AddPackagePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
    }

    public function rules(): array
    {
        return [
            'currency'  => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'cycle'     => ['required', Rule::in(WorkspaceSubscription::CYCLES)],
            'amount'    => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.exists'          => 'Choose one of the currencies in Admin > Currencies.',
            'starts_on.after_or_equal' => 'A new price starts today or later: the prices before stay as they were.',
        ];
    }
}
