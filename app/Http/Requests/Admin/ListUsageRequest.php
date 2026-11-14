<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\UsageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Usage's filters (Doc 15 Phase D2): words, plan (key), state, the limit it looks at, and the order. */
class ListUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'  => ['nullable', 'string', 'max:255'],
            'package' => ['nullable', 'string', Rule::exists('packages', 'key')],
            'state'   => ['nullable', Rule::in(array_keys(UsageService::STATES))],
            'limit'   => ['nullable', Rule::in(array_keys(UsageService::LIMITS))],
            'sort'    => ['nullable', Rule::in(array_keys(UsageService::SORTS))],
        ];
    }
}
