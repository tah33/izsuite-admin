<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Announcement;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A platform announcement (Doc 15 section 15.21 - Phase F1): its title,
 * message, audience and what it names, priority, link, whether it can be
 * dismissed, and its days (UTC).
 */
class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** An unticked box sends nothing: not dismissible. */
    protected function prepareForValidation(): void
    {
        $this->merge(['dismissible' => $this->boolean('dismissible')]);
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:150'],
            'message'     => ['required', 'string', 'max:1000'],
            'audience'    => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'packages'    => ['nullable', 'array'],
            'packages.*'  => [Rule::exists('packages', 'key')],
            'countries'   => ['nullable', 'array'],
            'countries.*' => [Rule::in(Countries::codes())],
            'apps'        => ['nullable', 'array'],
            'apps.*'      => [Rule::exists('apps', 'family_key')],
            'businesses'  => ['nullable', 'string', 'max:5000'],
            'priority'    => ['required', Rule::in(array_keys(Announcement::PRIORITIES))],
            // A web address, or a page of the app such as /settings?tab=subscription.
            'link_url'    => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/[^\s]+|\/[^\s]*)$/i'],
            'link_label'  => ['nullable', 'string', 'max:60'],
            'dismissible' => ['boolean'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'link_url.regex' => 'Give a web address starting with https://, or a page of the app starting with /.',
            'ends_at.after'  => 'The end comes after the start.',
        ];
    }
}
