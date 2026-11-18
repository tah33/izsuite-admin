<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Another pipeline, added in Settings > CRM (Doc 17 Phase D3b1): a name
     * used once in the business. It starts with the starting stages.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('crm_pipelines', 'name')->where('workspace_id', $workspace?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'The business has a pipeline with this name already.',
        ];
    }
}
