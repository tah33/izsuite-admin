@php($currentLangInfo = \App\Models\Admin\Language::where('code', app()->getLocale())->first())
@php($htmlDir = $currentLangInfo?->direction === 'rtl' ? 'rtl' : 'ltr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') - {{ setting('site_name', 'Resumist') }}</title>
    @if(setting('site_favicon'))
        <link rel="icon" href="{{ app(\App\Services\Support\ImageService::class)->publicUrl(setting('site_favicon')) }}">
    @else
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23137fec'%3E%3Cpath fill-rule='evenodd' d='M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a2.625 2.625 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a2.625 2.625 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5zM16.5 15a.75.75 0 01.712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 010 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 01-1.422 0l-.395-1.183a1.5 1.5 0 00-.948-.948l-1.183-.395a.75.75 0 010-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0116.5 15z' clip-rule='evenodd'%3E%3C/path%3E%3C/svg%3E">
    @endif
    @stack('meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php($primaryColor = setting('primary_color', '#137fec'))
    <style>
        :root {
            --primary: {{ $primaryColor }} !important;
            --primary-hover: color-mix(in srgb, var(--primary), black 15%) !important;
            --primary-light: color-mix(in srgb, var(--primary), white 85%) !important;
            --primary-dark: color-mix(in srgb, var(--primary), black 25%) !important;
        }
    </style>

    @stack('head')
    @stack('styles')
</head>
<body class="min-h-screen flex items-center justify-center bg-[var(--content-bg)]">
    <div class="w-full max-w-md mx-auto px-4 py-12">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                <x-site-logo class="w-[36px] h-[36px] text-[var(--primary)]" />
                <span class="text-xl font-bold text-[var(--text-primary)]">{{ setting('site_name', 'Resumist') }}</span>
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
