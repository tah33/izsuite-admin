<?php

namespace App\Http\Requests\Admin;

use App\Enums\Edition;
use App\Models\Frontend\Application;
use App\Repositories\Admin\AppRepository;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * app_key, family_key and edition are set once, when the app is made, and
     * never change: businesses' rows, URLs and records point at them. On an
     * edit they are taken from the app, whatever the form sends.
     *
     * A new standalone app is a family of one: family_key defaults to the
     * name's slug, and app_key to the family (with _lite for a Lite edition).
     *
     * An unticked "Active" box sends nothing, so it is read as false here -
     * otherwise an app could never be switched off from the form.
     */
    protected function prepareForValidation(): void
    {
        $app = $this->route('id') ? app(AppRepository::class)->find((int) $this->route('id')) : null;

        if ($app) {
            $this->merge([
                'app_key'    => $app->app_key,
                'family_key' => $app->family_key,
                'edition'    => $app->edition->value,
            ]);
        } else {
            $familyKey = trim((string) $this->input('family_key'));
            $family    = Str::slug($familyKey !== '' ? $familyKey : (string) $this->input('name'));
            $edition   = $this->input('edition') ?: Edition::Full->value;
            $appKey    = trim((string) $this->input('app_key'));

            $this->merge([
                'family_key' => $family,
                'edition'    => $edition,
                'app_key'    => $appKey !== '' ? $appKey : $family.($edition === Edition::Lite->value ? '_lite' : ''),
            ]);
        }

        $this->merge([
            'icon'       => trim((string) $this->input('icon')) ?: null,
            'sort_order' => $this->input('sort_order') ?? 0,
            'is_active'  => $this->boolean('is_active'),
            // The app page's main features (Doc 13 section 6): one a line in the form.
            'features'   => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input('features_text')) ?: []))) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string', 'max:5000'],
            'features'    => ['nullable', 'array', 'max:12'],
            'features.*'  => ['string', 'max:200'],
            'support_url' => ['nullable', 'url', 'max:255'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'category_id' => ['required', 'integer', Rule::exists('app_categories', 'id')],
            'icon'        => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            // Retired only by Retire (Doc 15 Phase E2), which turns it off everywhere; a retired one may keep it.
            'status'      => ['required', Rule::in(array_diff(array_keys(Application::STATUSES), $this->isRetired() ? [] : [Application::RETIRED]))],
            'countries'   => ['nullable', 'array'],
            'countries.*' => [Rule::in(Countries::codes())],
            'conflicts'   => ['nullable', 'array'],
            'conflicts.*' => ['string', Rule::exists('apps', 'family_key')],
            'release_version' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z][0-9A-Za-z.+-]*$/'],
            'is_active'   => ['boolean'],
            'app_key'     => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('apps', 'app_key')->ignore($this->route('id'))],
            'family_key'  => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'edition'     => [
                'required',
                Rule::enum(Edition::class),
                // One Lite and one Full per family.
                Rule::unique('apps', 'edition')
                    ->where('family_key', $this->input('family_key'))
                    ->ignore($this->route('id')),
            ],
            'sort_order'  => ['required', 'integer', 'min:0'],
        ];
    }

    /** The app being edited is retired already. */
    private function isRetired(): bool
    {
        return $this->route('id') !== null && app(AppRepository::class)->find((int) $this->route('id'))?->status === Application::RETIRED;
    }

    public function attributes(): array
    {
        return [
            'category_id'      => 'category',
            'long_description' => 'full description',
            'features.*'       => 'feature',
            'support_url'      => 'support link',
        ];
    }

    public function messages(): array
    {
        return [
            'edition.unique'   => __('This family already has an app with that edition.'),
            'app_key.regex'    => __('Lower-case letters, numbers, - and _ only, starting with a letter or number.'),
            'family_key.regex' => __('Lower-case letters, numbers, - and _ only, starting with a letter or number.'),
            'icon.regex'       => __('Use a lucide icon name, such as receipt.'),
        ];
    }
}
