<?php

namespace App\Http\Requests\Api\Workspaces;

use App\Models\Frontend\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing.
     *
     * The app is optional, and must be one that is on offer: an app an admin has
     * left active (the list GET /apps hands the form). null takes the workspace
     * off it; leaving the key out leaves it as it was.
     *
     * user_id is not accepted at all - the workspace is always the caller's, so
     * one in the body is ignored. Neither is a staff member: which staff work in
     * which workspace is set from the staff side (user_staff.assigned_workspaces),
     * so a staff_id in the body is ignored too.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'app_id' => ['nullable', 'bail', 'integer', Rule::exists(Application::class, 'id')->where('is_active', true)],
        ];
    }

    public function attributes(): array
    {
        return [
            'app_id' => 'app',
        ];
    }

    public function messages(): array
    {
        return [
            'app_id.exists' => 'Choose one of the available apps.',
        ];
    }
}
