<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand identity
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the izSuite brand. These values are used as
    | the fallbacks behind the admin-editable settings (`site_name`,
    | `primary_color`, `site_logo`, `site_favicon`), so a fresh install is
    | correctly branded before anyone touches the settings screen.
    |
    */

    'name'    => 'izSuite',
    'tagline' => 'All-in-One Business Software',

    /*
    |--------------------------------------------------------------------------
    | Palette
    |--------------------------------------------------------------------------
    |
    | `primary` is the brand blue and drives --primary in resources/css/app.css.
    | `ink` is the brand navy used for dark surfaces (sidebar, splash, social).
    |
    */

    'colors' => [
        'primary' => '#2563EB',
        'ink'     => '#0F172A',
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | Paths are relative to public/ and resolved with asset(). Regenerate them
    | with the brand generator rather than editing the SVGs by hand — the
    | wordmark is outlined Manrope ExtraBold, so it carries no font dependency.
    |
    | `logo`      full lockup (wordmark + tagline)
    | `wordmark`  wordmark only, for tight horizontal space
    | `mark`      the "iz" monogram, for square/avatar slots
    | `*_light`   reversed (white) variants, for dark backgrounds
    | `*_png`     raster variants, for email clients and PDF renderers
    |
    */

    'assets' => [
        'logo'             => 'images/brand/izsuite-logo.svg',
        'logo_light'       => 'images/brand/izsuite-logo-light.svg',
        'wordmark'         => 'images/brand/izsuite-wordmark.svg',
        'wordmark_light'   => 'images/brand/izsuite-wordmark-light.svg',
        'mark'             => 'images/brand/izsuite-mark.svg',
        'mark_light'       => 'images/brand/izsuite-mark-light.svg',

        'icon'             => 'images/brand/izsuite-icon.svg',
        'icon_blue'        => 'images/brand/izsuite-icon-blue.svg',
        'icon_dark'        => 'images/brand/izsuite-icon-dark.svg',

        'logo_png'           => 'images/brand/izsuite-logo.png',
        'logo_light_png'     => 'images/brand/izsuite-logo-light.png',
        'wordmark_png'       => 'images/brand/izsuite-wordmark.png',
        'wordmark_light_png' => 'images/brand/izsuite-wordmark-light.png',
        'mark_png'           => 'images/brand/izsuite-mark.png',
        'mark_light_png'     => 'images/brand/izsuite-mark-light.png',
        'icon_png'           => 'images/brand/icon-192.png',

        'favicon_ico'      => 'favicon.ico',
        'favicon_svg'      => 'favicon.svg',
        'apple_touch_icon' => 'apple-touch-icon.png',
        'manifest'         => 'site.webmanifest',
        'og_image'         => 'images/brand/og-image.png',
    ],

];
