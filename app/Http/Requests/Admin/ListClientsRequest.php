<?php

namespace App\Http\Requests\Admin;

use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Users list's filters (Doc 15 section 15.7): words, a plan one of its
 * businesses is on, the account's status, whether its e-mail is checked and
 * two-factor is on, and whether it has security alerts. Rows per page is read
 * by requested_per_page().
 */
class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'     => ['nullable', 'string', 'max:255'],
            'package'    => ['nullable', 'string', 'max:50'],
            'status'     => ['nullable', Rule::in([User::ACTIVE, User::LOCKED, User::INACTIVE])],
            'verified'   => ['nullable', Rule::in(['yes', 'no'])],
            'two_factor' => ['nullable', Rule::in(['on', 'off'])],
            'alerts'     => ['nullable', Rule::in(['yes'])],
        ];
    }

    /** @return array<string, string> the filters given */
    public function filters(): array
    {
        return array_filter([
            'search'     => trim((string) $this->validated('search')),
            'package'    => $this->validated('package'),
            'status'     => $this->validated('status'),
            'verified'   => $this->validated('verified'),
            'two_factor' => $this->validated('two_factor'),
            'alerts'     => $this->validated('alerts'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
