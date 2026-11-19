<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\MarketingConsent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A lead's or contact's answer about marketing (Doc 17 Phase I3): its
 * purpose - checked to be one of the business's switched on by
 * CrmConsentService - and channel, consent given or refused, where it came
 * from, when (the business's day and time, now when left out), a consent
 * given's expiry, and its evidence or reference.
 */
class RecordMarketingConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose_id'    => ['required', 'integer'],
            'channel'       => ['required', Rule::in(MarketingConsent::CHANNELS)],
            'status'        => ['required', Rule::in(MarketingConsent::ANSWERS)],
            'source'        => ['required', Rule::in(MarketingConsent::SOURCES)],
            'recorded_on'   => ['nullable', 'required_with:recorded_time', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'recorded_time' => ['nullable', 'date_format:H:i'],
            'expires_on'    => ['nullable', 'date_format:Y-m-d', 'before:2100-01-01', 'prohibited_unless:status,'.MarketingConsent::GIVEN],
            'evidence'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'purpose_id.required'           => 'Choose what it is about.',
            'channel.required'              => 'Choose the channel.',
            'status.required'               => 'Choose whether consent was given or refused.',
            'source.required'               => 'Choose where the answer came from.',
            'recorded_on.required_with'     => 'Choose the day with the time.',
            'expires_on.prohibited_unless'  => 'Only a consent given expires.',
        ];
    }
}
