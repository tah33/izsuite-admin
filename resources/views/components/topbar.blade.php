@php
    $activeLocale = session('admin_locale', setting('default_language', 'en'));
    $activeCurrency = session('admin_currency', setting('default_currency', 'USD'));
    $activeCurrModel = \App\Models\Admin\Currency::where('code', $activeCurrency)->first();
    $perPageOptions = [10, 15, 25, 50, 100];
    $currentPerPage = (int) request()->integer('per_page', 15);
    if (! in_array($currentPerPage, $perPageOptions, true)) {
        $currentPerPage = 15;
    }
@endphp
<header class="topbar sticky top-0 z-30">
    <div class="flex items-center gap-3">
        <button id="sidebar-toggle" class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100">
            <i data-lucide="menu" class="w-5 h-5 text-[var(--text-secondary)]"></i>
        </button>
        <span class="topbar-title">{{ $panelTitle ?? '' }}</span>
    </div>

    <div class="topbar-actions">
        <form method="GET" action="{{ url()->current() }}" class="hidden xl:flex items-center gap-2">
            @foreach(request()->except(['per_page', 'page']) as $key => $value)
                @if(! is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="text-xs font-medium text-[var(--text-muted)]">{{ __('Rows') }}</label>
            <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-[var(--card-border)] bg-white px-2 text-sm text-[var(--text-primary)]">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}" {{ $currentPerPage === $option ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                @endforeach
            </select>
        </form>

        <a href="{{ config('app.frontend_url') }}" target="_blank" rel="noopener noreferrer" class="topbar-dropdown" title="{{ __('Visit Website') }}">
            <i data-lucide="globe" class="w-4 h-4"></i>
        </a>
        <a href="{{ route('admin.cache-clear') }}" class="topbar-dropdown text-[var(--warning)] hover:bg-[var(--warning-light)]" title="{{ __('Clear System Cache') }}">
            <i data-lucide="sparkles" class="w-4 h-4"></i>
        </a>

        <div class="relative">
            <button id="lang-dropdown-toggle" class="topbar-dropdown">
                <span>{{ strtoupper($activeLocale) }}</span>
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
            </button>
            <div id="lang-dropdown-menu" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-50 border-[var(--card-border)]">
                @foreach(\App\Models\Admin\Language::where('is_active', true)->get() as $lang)
                    <form method="POST" action="{{ route('admin.switch-language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $lang->code }}">
                        <button type="submit" class="w-full text-start px-4 py-2 text-sm hover:bg-gray-50 {{ $activeLocale == $lang->code ? 'font-bold text-[var(--primary)]' : 'text-[var(--text-primary)]' }}">
                            {{ $lang->name }} ({{ strtoupper($lang->code) }})
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="relative">
            <button id="curr-dropdown-toggle" class="topbar-dropdown">
                <span>{{ $activeCurrModel ? $activeCurrModel->symbol : '$' }}</span> {{ strtoupper($activeCurrency) }}
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
            </button>
            <div id="curr-dropdown-menu" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-50 border-[var(--card-border)]">
                @foreach(\App\Models\Admin\Currency::where('is_active', true)->get() as $curr)
                    <form method="POST" action="{{ route('admin.switch-currency') }}">
                        @csrf
                        <input type="hidden" name="currency" value="{{ $curr->code }}">
                        <button type="submit" class="w-full text-start px-4 py-2 text-sm hover:bg-gray-50 {{ $activeCurrency == $curr->code ? 'font-bold text-[var(--primary)]' : 'text-[var(--text-primary)]' }}">
                            {{ $curr->name }} ({{ $curr->code }})
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="relative">
            <button id="user-dropdown-toggle" class="user-avatar">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            </button>

            <div id="user-dropdown-menu" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-50 border-[var(--card-border)]">
                <a href="{{ route('admin.profile') }}" class="block px-4 py-2 text-sm hover:bg-gray-50 text-[var(--text-primary)]">{{ __('My Profile') }}</a>
                <a href="{{ route('admin.settings.index') }}" class="block px-4 py-2 text-sm hover:bg-gray-50 text-[var(--text-primary)]">{{ __('Settings') }}</a>
                <div class="bg-[var(--card-border)] h-[1px]"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="cursor: pointer;" class="w-full text-start px-4 py-2 text-sm hover:bg-gray-50 text-[var(--danger)]">{{ __('Logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</header>
