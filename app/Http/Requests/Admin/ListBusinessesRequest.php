<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\BusinessService;
use App\Support\BusinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Businesses' filters (Doc 15 section 15.5). */
class ListBusinessesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'       => ['nullable', 'string', 'max:100'],
            'status'       => ['nullable', Rule::in(array_keys(BusinessStatus::LABELS))],
            'package'      => ['nullable', 'string', 'max:50'],
            'country'      => ['nullable', 'string', 'size:2'],
            'subscription' => ['nullable', Rule::in(array_keys(BusinessService::SUBSCRIPTION_LABELS))],
            'from'         => ['nullable', 'date_format:Y-m-d'],
            'to'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'limits'       => ['nullable', Rule::in(['reached'])],
            'per_page'     => ['nullable', 'integer', Rule::in([10, 15, 25, 50, 100])],
        ];
    }

    /** The filters given, without the page size. */
    public function filters(): array
    {
        return array_filter($this->safe()->except('per_page'), fn ($value) => $value !== null && $value !== '');
    }
}
