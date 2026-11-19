<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmScoringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A lead scoring rule (Doc 17 Phase I2): its name, whether it is on, its
 * factor with what the factor asks for (CrmScoringRule::NEEDS) - a lead
 * source, location or item, an option, or a threshold - and its points, from
 * -100 to 100 and never 0. The ids are checked to be the business's own by
 * CrmScoringRuleService, which keeps only what the factor asks for.
 */
class SaveCrmScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $factor = $this->input('factor');

        return [
            'name'        => ['required', 'string', 'max:100'],
            'is_active'   => ['sometimes', 'boolean'],
            'factor'      => ['required', Rule::in(CrmScoringRule::FACTORS)],
            'source_id'   => ['nullable', 'required_if:factor,source', 'integer'],
            'location_id' => ['nullable', 'required_if:factor,location', 'integer'],
            'item_id'     => ['nullable', 'required_if:factor,item', 'integer'],
            'option'      => ['nullable', 'required_if:factor,detail,purchase_period,business_size,response', 'string', 'max:100', ...match ($factor) {
                'detail'          => [Rule::in(CrmScoringRule::DETAILS)],
                'purchase_period' => [Rule::in(CrmLead::PURCHASE_PERIODS)],
                'response'        => [Rule::in(CrmScoringRule::RESPONSES)],
                default           => [],
            }],
            'threshold'   => ['nullable', 'required_if:factor,value,budget,engagement,quiet', 'numeric', ...match ($factor) {
                'engagement' => ['integer', 'min:1', 'max:1000'],
                'quiet'      => ['integer', 'min:1', 'max:3650'],
                default      => ['gt:0', 'max:9999999999999.99'],
            }],
            'points'      => ['required', 'integer', 'between:-100,100', 'not_in:0'],
        ];
    }

    public function messages(): array
    {
        $factor = $this->input('factor');

        return [
            'source_id.required_if'   => 'Choose the lead source.',
            'location_id.required_if' => 'Choose the location.',
            'item_id.required_if'     => 'Choose the item.',
            'option.required_if'      => match ($factor) {
                'purchase_period' => 'Choose the purchase period.',
                'business_size'   => 'Type the words its business size or type must have.',
                'response'        => 'Choose contacted or not contacted in time.',
                default           => 'Choose the detail.',
            },
            'threshold.required_if'   => match ($factor) {
                'engagement' => 'Enter how many activities and e-mails.',
                'quiet'      => 'Enter the days.',
                default      => 'Enter the amount.',
            },
            'threshold.integer'       => 'Enter a whole number.',
            'threshold.gt'            => 'Enter an amount above zero.',
            'points.between'          => 'Points go from -100 to 100.',
            'points.not_in'           => 'Give it points: more, or fewer with a minus.',
        ];
    }
}
