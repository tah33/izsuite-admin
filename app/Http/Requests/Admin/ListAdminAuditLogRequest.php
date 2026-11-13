<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\AdminAuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The audit log's filters (Doc 15 section 15.26): for its page and its export. */
class ListAdminAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'    => ['nullable', 'integer'],
            'action'     => ['nullable', 'string', 'max:30'],
            'model_type' => ['nullable', 'string', 'max:255'],
            'result'     => ['nullable', Rule::in(array_keys(AdminAuditLog::RESULTS))],
            'from'       => ['nullable', 'date_format:Y-m-d'],
            'to'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search'     => ['nullable', 'string', 'max:100'],
            'per_page'   => ['nullable', 'integer', Rule::in([10, 15, 25, 50, 100])],
        ];
    }

    /** The filters given, without the page size. */
    public function filters(): array
    {
        return array_filter($this->safe()->except('per_page'), fn ($value) => $value !== null && $value !== '');
    }
}
