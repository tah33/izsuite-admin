<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A pipeline as Settings > CRM saves it (Doc 17 Phase A2): a name used
     * once in the business, and its stages in order - each a name used once
     * in it, a default probability from 0 to 100, open, won or lost, and on
     * or off. A stage it has keeps its id. Phase D3b1: the pipeline itself
     * on or off - left out, as it is.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'                 => ['required', 'string', 'max:100', Rule::unique('crm_pipelines', 'name')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'is_active'            => ['sometimes', 'boolean'],
            'stages'               => ['required', 'array', 'min:3', 'max:30'],
            'stages.*.id'          => ['nullable', 'integer'],
            'stages.*.name'        => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'stages.*.probability' => ['required', 'integer', 'min:0', 'max:100'],
            'stages.*.category'    => ['required', Rule::in(array_keys(CrmStage::CATEGORIES))],
            'stages.*.is_active'   => ['sometimes', 'boolean'],
            // Phase D3a: an open stage's stale-after period, and the details any stage needs before one enters it.
            'stages.*.stale_after_days'  => ['nullable', 'integer', 'min:1', 'max:'.CrmStage::MAX_STALE_DAYS],
            'stages.*.required_fields'   => ['nullable', 'array'],
            'stages.*.required_fields.*' => ['string', 'distinct', Rule::in(array_keys(CrmStage::REQUIRABLE))],
        ];
    }

    /** An opportunity can always be open, won or lost: a stage of each stays on. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $on = collect($this->input('stages', []))->filter(fn ($stage) => is_array($stage) && ($stage['is_active'] ?? true));

            foreach (array_keys(CrmStage::CATEGORIES) as $category) {
                if (! $on->contains('category', $category)) {
                    $validator->errors()->add('stages', 'A pipeline needs an open stage, a won stage and a lost stage, each switched on.');

                    return;
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.unique'            => 'Another pipeline has this name.',
            'stages.min'             => 'A pipeline needs an open stage, a won stage and a lost stage, each switched on.',
            'stages.*.name.distinct' => 'Each stage needs a name of its own.',
            'stages.*.name.required' => 'Give the stage a name.',
        ];
    }
}
