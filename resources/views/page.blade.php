@php
    $primaryColor = setting('primary_color', config('brand.colors.primary'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="page-frontend">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @if($page->meta_title)
        <title>{{ $page->meta_title }}</title>
    @else
        <title>{{ $page->title }} - {{ $siteName }}</title>
    @endif

    <x-brand-head :root-vars="false" />

    @if($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if($page->meta_keywords)
        <meta name="keywords" content="{{ $page->meta_keywords }}">
    @endif

    <meta property="og:title" content="{{ $page->meta_title ?: $page->title }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:image" content="{{ asset(config('brand.assets.og_image')) }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset(config('brand.assets.og_image')) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Page styles --}}
    <link rel="stylesheet" href="{{ asset('css/page.css') }}">
    {{-- Override primary color from site settings --}}
    <style>.page-frontend { --pf-primary: {{ $primaryColor }}; }</style>
</head>
<body>
    <header class="page-header">
        <div class="page-header-inner">
            <a href="{{ route('home') }}" class="site-name">{{ $siteName }}</a>
            <a href="{{ route('home') }}" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                {{ __('Back') }}
            </a>
        </div>
    </header>

    <main class="page-main">
        <h1 class="page-title">{{ $page->title }}</h1>

        <article class="page-content">
            {!! $page->content !!}
        </article>
    </main>

    <footer class="page-footer">
        &copy; {{ date('Y') }} {{ $siteName }}
    </footer>
</body>
</html>
