@php($currentLangInfo = \App\Models\Admin\Language::where('code', app()->getLocale())->first())
@php($htmlDir = $currentLangInfo?->direction === 'rtl' ? 'rtl' : 'ltr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admin')) - {{ setting('site_name', config('brand.name')) }}</title>
    @stack('meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-brand-head />

    @stack('head')
    @stack('styles')
</head>
<body class="min-h-screen bg-[var(--content-bg)]">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>

    <div class="flex min-h-screen">
        @hasSection('sidebar')
            @yield('sidebar')
        @else
            @include('components.admin-sidebar')
        @endif

        <div class="flex-1 flex flex-col main-content-layout">
            @hasSection('topbar')
                @yield('topbar')
            @else
                @include('components.topbar', ['panelTitle' => 'Admin Panel'])
            @endif

            <main class="flex-1 p-6">
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

                @yield('breadcrumbs')
                @yield('content')
            </main>

            <footer class="px-6 py-4 text-center text-xs text-[var(--text-muted)] border-t border-[var(--card-border)]">
                &copy; {{ date('Y') }} {{ setting('site_name', config('brand.name')) }} - Admin Panel
            </footer>
        </div>
    </div>

    @stack('scripts')

    <script>
        lucide.createIcons();

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if ((form.method || 'get').toLowerCase() !== 'get') {
                return;
            }

            if (form.querySelector('input[name="per_page"], select[name="per_page"]')) {
                return;
            }

            var currentUrl = new URL(window.location.href);
            var perPage = currentUrl.searchParams.get('per_page');

            if (!perPage) {
                return;
            }

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'per_page';
            hidden.value = perPage;
            form.appendChild(hidden);
        }, true);

        function openConfirmModal(id) {
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('open');
                lucide.createIcons();
                document.body.style.overflow = 'hidden';
            }
        }

        function closeConfirmModal(id, event) {
            if (event && event.target !== event.currentTarget) return;
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.confirm-modal-overlay.open').forEach(function(m) {
                    m.classList.remove('open');
                    document.body.style.overflow = '';
                });
            }
        });
    </script>
</body>
</html>
