@props([
    'rootVars' => true,   // emit the :root --primary override (admin layouts); off for the
                          // public page layout, which scopes its own --pf-primary
])

@php
    $faviconPath = setting('site_favicon');
    $faviconUrl  = $faviconPath
        ? app(\App\Services\Support\ImageService::class)->publicUrl($faviconPath)
        : null;

    // Validated on save by UpdateSettingRequest, but this value lands inside a
    // <style> block — re-check it here so a hand-edited row cannot inject CSS.
    $primaryColor = setting('primary_color', config('brand.colors.primary'));

    if (! preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $primaryColor)) {
        $primaryColor = config('brand.colors.primary');
    }
@endphp

@if($faviconUrl)
    <link rel="icon" href="{{ $faviconUrl }}">
@else
    <link rel="icon" href="{{ asset(config('brand.assets.favicon_ico')) }}" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="{{ asset(config('brand.assets.favicon_svg')) }}">
    <link rel="apple-touch-icon" href="{{ asset(config('brand.assets.apple_touch_icon')) }}">
    <link rel="manifest" href="{{ asset(config('brand.assets.manifest')) }}">
@endif

<meta name="theme-color" content="{{ $primaryColor }}">

@if($rootVars)
    <style>
        /* Admin-configurable primary. The alpha ramp, sidebar accents and focus
           rings in app.css all derive from --primary, so this one value is enough. */
        :root {
            --primary: {{ $primaryColor }} !important;
            --primary-hover: color-mix(in srgb, var(--primary), black 15%) !important;
            --primary-light: color-mix(in srgb, var(--primary), white 85%) !important;
            --primary-dark: color-mix(in srgb, var(--primary), black 25%) !important;
        }
    </style>
@endif
