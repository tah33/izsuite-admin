<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\Admin\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * The exists rule does tell a caller which addresses are registered. That
     * was weighed and accepted: the register endpoint already answers the same
     * question - a signup form has to say "this email is taken" - so staying
     * vague here bought very little and cost a real user any way of telling a
     * typo from a delivery problem.
     *
     * Every other role is excluded rather than rejected further in, so an
     * out-of-scope address and an unregistered one come back identical - same
     * status, same body, same field. Answering them differently would make
     * this a way to pick those accounts out of a list of addresses, and it
     * would leave the frontend with two shapes to handle for one situation.
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'email', 'max:255',
                Rule::exists('users', 'email')->whereIn('role_id', $this->userRoleIds()),
            ],
        ];
    }

    /**
     * Mirrors User::isUser(): only the plain frontend account role may reset a
     * password through this public endpoint - not admins, and not the other
     * non-admin roles (recruiter, candidate) that share the users table. Read
     * by slug so renumbering the table cannot quietly open this up.
     *
     * @return array<int, int>
     */
    private function userRoleIds(): array
    {
        return Role::where('slug', 'user')->pluck('id')->all();
    }

    public function messages(): array
    {
        return [
            'email.exists' => "We couldn't find an account with that email address.",
        ];
    }
}
