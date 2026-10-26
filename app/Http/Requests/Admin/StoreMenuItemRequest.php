<?php

namespace App\Http\Requests\Admin;

use App\Enums\Edition;
use App\Models\Frontend\MenuItem;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Admin\MenuItemRepository;
use App\Services\User\Apps\ShowIfRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A Menu Builder item, by the plan's rules:
 *   - an app item belongs to a family and has a minimum edition, and its route
 *     starts with the family key (/sales/...);
 *   - Core and Bottom items have neither;
 *   - a parent is a top-level item in the same place - one level of nesting;
 *   - Show if is one of the rules in code, never typed.
 */
class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isApp = $this->input('section') === MenuItem::SECTION_APPS;
        $route = trim((string) $this->input('route'));

        $this->merge([
            'family_key'     => $isApp ? ($this->input('family_key') ?: null) : null,
            'min_edition'    => $isApp ? ($this->input('min_edition') ?: Edition::Lite->value) : null,
            'parent_id'      => $this->input('parent_id') ?: null,
            'route'          => $route !== '' ? '/'.ltrim($route, '/') : null,
            'icon'           => trim((string) $this->input('icon')) ?: null,
            'permission_key' => trim((string) $this->input('permission_key')) ?: null,
            'show_if'        => $this->input('show_if') ?: null,
            'sort_order'     => $this->input('sort_order') ?? 0,
            'is_active'      => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'section'        => ['required', Rule::in(array_keys(MenuItem::SECTIONS))],
            'family_key'     => ['nullable', 'required_if:section,'.MenuItem::SECTION_APPS, 'string', Rule::exists('apps', 'family_key')],
            'min_edition'    => ['nullable', Rule::enum(Edition::class)],
            'parent_id'      => ['nullable', 'integer', Rule::exists('menu_items', 'id')],
            'label'          => ['required', 'string', 'max:255'],
            'icon'           => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'route'          => ['nullable', 'string', 'max:255', 'regex:/^\/[a-z0-9\/_-]*$/'],
            'sort_order'     => ['required', 'integer', 'min:0'],
            'permission_key' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9._-]+$/'],
            'show_if'        => ['nullable', Rule::in(array_keys(app(ShowIfRules::class)->options()))],
            'is_active'      => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $family = $this->input('family_key');
                $route  = $this->input('route');

                if ($family !== null && $route !== null && ! Str::startsWith($route.'/', '/'.$family.'/')) {
                    $validator->errors()->add('route', __('An app item\'s route starts with its family: /:family/...', ['family' => $family]));
                }

                if ($family !== null && $this->input('min_edition') === Edition::Full->value
                    && ! app(AppRepository::class)->hasEdition($family, Edition::Full)) {
                    $validator->errors()->add('min_edition', __('This family has no Full edition.'));
                }

                if ($parentId = $this->input('parent_id')) {
                    $parent = app(MenuItemRepository::class)->find((int) $parentId);

                    if ($parent->parent_id !== null
                        || $parent->section !== $this->input('section')
                        || $parent->family_key !== $family
                        || (int) $parentId === (int) $this->route('id')) {
                        $validator->errors()->add('parent_id', __('A parent must be a top-level item in the same place.'));
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'family_key.required_if' => __('Choose the app family this item belongs to.'),
            'route.regex'            => __('A path such as /sales/invoices: lower-case letters, numbers, /, - and _.'),
            'icon.regex'             => __('Use a lucide icon name, such as file-text.'),
            'permission_key.regex'   => __('A permission key such as sales.invoice.view.'),
        ];
    }
}
