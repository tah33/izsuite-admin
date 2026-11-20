<?php

namespace App\Support;

/**
 * A storefront's theme as it is kept (Doc 19 section 19.7 - Phase C4a): its
 * logo and favicon (ids of its own pictures), its colours, its font, its
 * header's layout and announcement, its product cards' style and picture
 * shape, its footer's words, its address and opening hours, and its social
 * links. From Phase C4b, its home page's sections, in their order; from
 * Phase C6a, its search and sharing: the home's title and description - the
 * description, too, of a page with none of its own - its sharing picture,
 * and whether search engines may list it. What is not given is the default.
 */
final class StorefrontTheme
{
    /** Font stacks the storefront's pages use: none fetched from elsewhere. */
    public const FONTS = ['system', 'sans', 'serif', 'rounded'];

    public const HEADER_LAYOUTS = ['left', 'centered'];

    public const CARD_STYLES = ['standard', 'minimal'];

    public const CARD_IMAGES = ['square', 'portrait', 'landscape'];

    public const NETWORKS = ['facebook', 'instagram', 'x', 'youtube', 'tiktok', 'linkedin', 'whatsapp'];

    /**
     * Phase C4b: a home page's sections - a banner (a picture of its own, a
     * heading, a line and a button), collections, the menu's categories,
     * products (the newest or a collection's) and words of its own - each
     * with what it keeps.
     */
    public const SECTIONS = [
        'banner'      => ['heading', 'picture', 'text', 'button', 'link'],
        'collections' => ['heading', 'ids'],
        'categories'  => ['heading'],
        'products'    => ['heading', 'source', 'collection', 'count'],
        'text'        => ['heading', 'body'],
    ];

    public const MAX_SECTIONS = 10;

    /** Phase C4b: where a banner's button goes. */
    public const LINKS = ['shop', 'category', 'collection'];

    /** Phase C4b: which products a products section shows, and how many. */
    public const PRODUCT_SOURCES = ['newest', 'collection'];

    public const PRODUCT_COUNTS = [4, 8, 12];

    /** Phase C4b: how many collections a collections section may choose. */
    public const MAX_CHOSEN = 12;

    public const DEFAULTS = [
        'logo'    => null,
        'favicon' => null,
        'colors'  => ['primary' => '#1769e8', 'background' => '#f8fafc', 'text' => '#0f172a'],
        'font'    => 'system',
        'header'  => ['layout' => 'left', 'announcement' => null],
        'card'    => ['style' => 'standard', 'image' => 'square'],
        'footer'  => ['text' => null],
        'contact' => ['address' => null, 'hours' => null],
        'social'  => [],
        // The home as it was before its sections could be chosen: the store's name, its collections, its categories, what is new.
        'home'    => [
            ['type' => 'banner', 'heading' => null, 'picture' => null, 'text' => null, 'button' => 'Shop all', 'link' => ['to' => 'shop', 'id' => null]],
            ['type' => 'collections', 'heading' => 'Collections', 'ids' => []],
            ['type' => 'categories', 'heading' => 'Categories'],
            ['type' => 'products', 'heading' => 'New in', 'source' => 'newest', 'collection' => null, 'count' => 8],
        ],
        // Phase C6a: the home's title (none: the store's name) and description, its sharing picture, and whether search engines list it.
        'seo'     => ['title' => null, 'description' => null, 'image' => null, 'index' => true],
    ];

    /** What is a list - taken whole when given - not fields over the default's. */
    private const LISTS = ['social', 'home'];

    /** $theme over the defaults: what it does not say, the default's. */
    public static function of(?array $theme): array
    {
        $theme ??= [];
        $whole = [];

        foreach (self::DEFAULTS as $key => $default) {
            $given       = $theme[$key] ?? null;
            $whole[$key] = is_array($default) && ! in_array($key, self::LISTS, true)
                ? array_replace($default, array_intersect_key(is_array($given) ? $given : [], $default))
                : ($given ?? $default);
        }

        return $whole;
    }

    /** The ids of the pictures it shows - its logo, favicon and, from Phase C4b, its banners'; from Phase C6a, its sharing picture. @return list<int> */
    public static function fileIds(array $theme): array
    {
        $banners = array_map(fn (array $section) => $section['picture'] ?? null, array_filter($theme['home'] ?? [], fn (array $section) => ($section['type'] ?? null) === 'banner'));

        return array_values(array_unique(array_filter([$theme['logo'] ?? null, $theme['favicon'] ?? null, ...$banners, $theme['seo']['image'] ?? null], fn ($id) => $id !== null)));
    }
}
