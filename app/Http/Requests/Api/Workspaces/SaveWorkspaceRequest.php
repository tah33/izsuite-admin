<?php

namespace App\Http\Requests\Api\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class SaveWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing: only the name.
     *
     * A business's apps are not set here. It starts on Billing Starter with
     * that plan's Lite apps on, and turns apps on and off in the Apps Center
     * (POST /apps/{key}/activate); its plan is not the caller's to set at all.
     * So an app_id, package_id or nav_version in the body is ignored.
     *
     * user_id is not accepted either - the workspace is always the caller's.
     * Neither is a staff member: which staff work in which workspace is set
     * from the staff side (user_staff.assigned_workspaces), so a staff_id in the
     * body is ignored too.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
