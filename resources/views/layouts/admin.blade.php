@php($currentLangInfo = \App\Models\Admin\Language::where('code', app()->getLocale())->first())
@php($htmlDir = $currentLangInfo?->direction === 'rtl' ? 'rtl' : 'ltr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admin')) - {{ setting('site_name', 'Resumist') }}</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @php($primaryColor = setting('primary_color', '#137fec'))
    <style>
        :root {
            --primary: {{ $primaryColor }} !important;
            --primary-hover: color-mix(in srgb, var(--primary), black 15%) !important;
            --primary-light: color-mix(in srgb, var(--primary), white 85%) !important;
            --primary-dark: color-mix(in srgb, var(--primary), black 25%) !important;
            --sidebar-active-border: var(--primary) !important;
            --sidebar-active-bg: color-mix(in srgb, var(--primary), transparent 90%) !important;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--primary), transparent 65%) !important;
        }

        .sidebar:hover::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, 
                color-mix(in srgb, var(--primary), transparent 75%), 
                color-mix(in srgb, var(--primary), transparent 55%)
            ) !important;
        }

        .sidebar:hover::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, 
                color-mix(in srgb, var(--primary), transparent 60%), 
                color-mix(in srgb, var(--primary), transparent 40%)
            ) !important;
        }
    </style>

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
                &copy; {{ date('Y') }} {{ setting('site_name', 'Resumist') }} - Admin Panel
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
