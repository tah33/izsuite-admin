<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Support\StorefrontTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A storefront's draft theme (Doc 19 Phase C4a): its logo and favicon among
 * its own pictures, its colours, font, header, product cards, footer,
 * address, hours and social links - and, from Phase C4b, its home page's
 * sections, up to ten. Whether its pictures are its own, and the categories
 * and collections its sections name its business's, is
 * StorefrontThemeService's to check.
 */
class SaveStorefrontThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $colour = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'logo'                => ['nullable', 'integer'],
            'favicon'             => ['nullable', 'integer'],
            'colors'              => ['required', 'array'],
            'colors.primary'      => $colour,
            'colors.background'   => $colour,
            'colors.text'         => $colour,
            'font'                => ['required', Rule::in(StorefrontTheme::FONTS)],
            'header'              => ['required', 'array'],
            'header.layout'       => ['required', Rule::in(StorefrontTheme::HEADER_LAYOUTS)],
            'header.announcement' => ['nullable', 'string', 'max:160'],
            'card'                => ['required', 'array'],
            'card.style'          => ['required', Rule::in(StorefrontTheme::CARD_STYLES)],
            'card.image'          => ['required', Rule::in(StorefrontTheme::CARD_IMAGES)],
            'footer'              => ['nullable', 'array'],
            'footer.text'         => ['nullable', 'string', 'max:500'],
            'contact'             => ['nullable', 'array'],
            'contact.address'     => ['nullable', 'string', 'max:300'],
            'contact.hours'       => ['nullable', 'string', 'max:200'],
            'social'              => ['nullable', 'array', 'max:'.count(StorefrontTheme::NETWORKS)],
            'social.*.network'    => ['required', Rule::in(StorefrontTheme::NETWORKS)],
            'social.*.url'        => ['required', 'string', 'max:300', 'url:https'],
            // Phase C4b: the home page's sections, each keeping what its type does.
            'home'                => ['nullable', 'array', 'max:'.StorefrontTheme::MAX_SECTIONS],
            'home.*'              => ['array'],
            'home.*.type'         => ['required', Rule::in(array_keys(StorefrontTheme::SECTIONS))],
            'home.*.heading'      => ['nullable', 'string', 'max:80'],
            'home.*.picture'      => ['nullable', 'integer'],
            'home.*.text'         => ['nullable', 'string', 'max:200'],
            'home.*.button'       => ['nullable', 'string', 'max:30'],
            'home.*.link'         => ['nullable', 'array'],
            'home.*.link.to'      => ['nullable', Rule::in(StorefrontTheme::LINKS)],
            'home.*.link.id'      => ['nullable', 'integer'],
            'home.*.ids'          => ['nullable', 'array', 'max:'.StorefrontTheme::MAX_CHOSEN],
            'home.*.ids.*'        => ['integer', 'distinct'],
            'home.*.source'       => ['nullable', 'required_if:home.*.type,products', Rule::in(StorefrontTheme::PRODUCT_SOURCES)],
            'home.*.collection'   => ['nullable', 'required_if:home.*.source,collection', 'integer'],
            'home.*.count'        => ['nullable', 'required_if:home.*.type,products', Rule::in(StorefrontTheme::PRODUCT_COUNTS)],
            'home.*.body'         => ['nullable', 'required_if:home.*.type,text', 'string', 'max:1000'],
            // Phase C6a: its search and sharing.
            'seo'                 => ['nullable', 'array'],
            'seo.title'           => ['nullable', 'string', 'max:200'],
            'seo.description'     => ['nullable', 'string', 'max:320'],
            'seo.image'           => ['nullable', 'integer'],
            'seo.index'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'colors.*.regex'                => 'Give the colour as # and six hex digits, such as #1769e8.',
            'header.announcement.max'       => 'Keep the announcement to 160 characters.',
            'social.*.url.required'         => 'Give the link.',
            'social.*.url.url'              => 'Give the link starting https://.',
            'home.max'                      => 'A home page keeps up to '.StorefrontTheme::MAX_SECTIONS.' sections.',
            'seo.title.max'                 => 'Keep the title to 200 characters.',
            'seo.description.max'           => 'Keep the description to 320 characters.',
            'home.*.heading.max'            => 'Keep the heading to 80 characters.',
            'home.*.text.max'               => 'Keep the line to 200 characters.',
            'home.*.button.max'             => "Keep the button's words to 30 characters.",
            'home.*.ids.max'                => 'Choose up to '.StorefrontTheme::MAX_CHOSEN.' collections.',
            'home.*.collection.required_if' => 'Choose its collection.',
            'home.*.body.required_if'       => 'Write its words.',
            'home.*.body.max'               => 'Keep its words to 1,000 characters.',
        ];
    }
}
