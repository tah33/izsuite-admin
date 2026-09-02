@php($currentLangInfo = \App\Models\Admin\Language::where('code', app()->getLocale())->first())
@php($htmlDir = $currentLangInfo?->direction === 'rtl' ? 'rtl' : 'ltr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') - {{ setting('site_name', config('brand.name')) }}</title>
    @stack('meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-brand-head />

    @stack('head')
    @stack('styles')
</head>
<body class="min-h-screen flex items-center justify-center bg-[var(--content-bg)]">
    <div class="w-full max-w-md mx-auto px-4 py-12">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2">
                @if(setting('site_logo'))
                    <x-site-logo class="w-[36px] h-[36px]" />
                    <span class="text-xl font-bold text-[var(--text-primary)]">{{ setting('site_name', config('brand.name')) }}</span>
                @else
                    {{-- Default brand lockup already contains the name, so no text beside it --}}
                    <x-site-logo variant="full" class="h-14 w-auto" />
                @endif
            </a>
        </div>

        @if(session('success'))
            <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--success-light)] text-[var(--primary-dark)]">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            @yield('content')
        </div>

        @yield('below-card')
    </div>

    @stack('scripts')
    <script>lucide.createIcons();</script>
</body>
</html>
