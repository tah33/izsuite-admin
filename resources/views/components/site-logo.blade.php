@props([
    'class'   => 'w-8 h-8',
    'variant' => 'mark',    // mark | wordmark | full
    'tone'    => 'dark',    // dark = brand colours (light bg) | light = reversed white (dark bg)
    'raster'  => false,     // true = PNG, for email clients / PDF renderers
])

@php
    $logoPath = setting('site_logo');
    $logoUrl  = $logoPath ? app(\App\Services\Support\ImageService::class)->publicUrl($logoPath) : null;

    if (! $logoUrl) {
        $key = match ($variant) {
            'full'     => 'logo',
            'wordmark' => 'wordmark',
            default    => 'mark',
        };

        if ($tone === 'light') {
            $key .= '_light';
        }

        if ($raster) {
            $key .= '_png';
        }

        // Every variant/tone/raster combination is mapped in config/brand.php;
        // the default keeps an unknown combination on the lockup, not a 404.
        $logoUrl = asset(config("brand.assets.{$key}", config('brand.assets.logo')));
    }
@endphp

<img src="{{ $logoUrl }}"
     alt="{{ setting('site_name', config('brand.name')) }}"
     class="{{ $class }} object-contain">
