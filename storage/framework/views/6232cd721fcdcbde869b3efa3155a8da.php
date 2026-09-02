<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'class'   => 'w-8 h-8',
    'variant' => 'mark',    // mark | wordmark | full
    'tone'    => 'dark',    // dark = brand colours (light bg) | light = reversed white (dark bg)
    'raster'  => false,     // true = PNG, for email clients / PDF renderers
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'class'   => 'w-8 h-8',
    'variant' => 'mark',    // mark | wordmark | full
    'tone'    => 'dark',    // dark = brand colours (light bg) | light = reversed white (dark bg)
    'raster'  => false,     // true = PNG, for email clients / PDF renderers
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
?>

<img src="<?php echo e($logoUrl); ?>"
     alt="<?php echo e(setting('site_name', config('brand.name'))); ?>"
     class="<?php echo e($class); ?> object-contain">
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/components/site-logo.blade.php ENDPATH**/ ?>