<aside id="sidebar" class="sidebar fixed top-0 start-0 flex flex-col z-40">
    <div class="sidebar-logo">
        <a href="{{ route('admin.overview') }}" class="flex items-center justify-center gap-2.5">
            @if(setting('site_logo'))
                <x-site-logo class="w-7 h-7" />
                <span class="logo-text block leading-tight">{{ setting('site_name', config('brand.name')) }}</span>
            @else
                {{-- Reversed wordmark: the sidebar is a dark surface, and the mark already reads as the name --}}
                <x-site-logo variant="wordmark" tone="light" class="h-[22px] w-auto" />
            @endif
        </a>
    </div>

    <nav id="sidebar-nav" class="flex-1 py-4 overflow-y-auto">
        <a href="{{ route('admin.overview') }}" class="sidebar-link {{ request()->routeIs('admin.overview') ? 'active' : '' }}">
            <i data-lucide="layout-dashboard" class="icon"></i>
            <span>{{ __('Overview') }}</span>
        </a>

        <a href="{{ route('admin.staff.index') }}" class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
            <i data-lucide="user-check" class="icon"></i>
            <span>{{ __('Staff') }}</span>
        </a>

        <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <i data-lucide="shield" class="icon"></i>
            <span>{{ __('Roles') }}</span>
        </a>

        <a href="{{ route('admin.tickets.index') }}" class="sidebar-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
            <i data-lucide="ticket" class="icon"></i>
            <span>{{ __('Tickets') }}</span>
        </a>

        <div class="sidebar-section-title">{{ __('Management') }}</div>

        <a href="{{ route('admin.app-categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.app-categories.*') ? 'active' : '' }}">
            <i data-lucide="tags" class="icon"></i>
            <span>{{ __('App Categories') }}</span>
        </a>

        <a href="{{ route('admin.apps.index') }}" class="sidebar-link {{ request()->routeIs('admin.apps.*') ? 'active' : '' }}">
            <i data-lucide="app-window" class="icon"></i>
            <span>{{ __('Apps') }}</span>
        </a>


        <a href="{{ route('admin.departments.index') }}" class="sidebar-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
            <i data-lucide="layers" class="icon"></i>
            <span>{{ __('Departments') }}</span>
        </a>

        <a href="{{ route('admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
            <i data-lucide="tag" class="icon"></i>
            <span>{{ __('Plans & Pricing') }}</span>
        </a>

        <a href="{{ route('admin.subscriptions.index') }}" class="sidebar-link {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
            <i data-lucide="history" class="icon"></i>
            <span>{{ __('Subscription History') }}</span>
        </a>

        <a href="{{ route('admin.payment-methods.index') }}" class="sidebar-link {{ request()->routeIs('admin.payment-methods.*') ? 'active' : '' }}">
            <i data-lucide="wallet" class="icon"></i>
            <span>{{ __('Payment Methods') }}</span>
        </a>

        <a href="{{ route('admin.faqs.index') }}" class="sidebar-link {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}">
            <i data-lucide="circle-help" class="icon"></i>
            <span>{{ __('FAQs') }}</span>
        </a>

        <a href="{{ route('admin.contact-messages.index') }}" class="sidebar-link {{ request()->routeIs('admin.contact-messages.*') ? 'active' : '' }}">
            <i data-lucide="mail-plus" class="icon"></i>
            <span>{{ __('Contact Messages') }}</span>
        </a>

        <div class="sidebar-section-title">{{ __('Frontend CMS') }}</div>

        <a href="{{ route('admin.content.index', ['context' => 'header-footer']) }}" class="sidebar-link {{ request()->routeIs('admin.content.*') ? 'active' : '' }}">
            <i data-lucide="panels-top-left" class="icon"></i>
            <span>{{ __('Header & Footer') }}</span>
        </a>

        <a href="{{ route('admin.pages.index') }}" class="sidebar-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
            <i data-lucide="file-text" class="icon"></i>
            <span>{{ __('Pages') }}</span>
        </a>

        <div class="sidebar-section-title">{{ __('System') }}</div>

        <a href="{{ route('admin.languages.index') }}" class="sidebar-link {{ request()->routeIs('admin.languages.*') ? 'active' : '' }}">
            <i data-lucide="globe" class="icon"></i>
            <span>{{ __('Languages') }}</span>
        </a>

        <a href="{{ route('admin.currencies.index') }}" class="sidebar-link {{ request()->routeIs('admin.currencies.*') ? 'active' : '' }}">
            <i data-lucide="coins" class="icon"></i>
            <span>{{ __('Currencies') }}</span>
        </a>

        <a href="{{ route('admin.settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <i data-lucide="settings" class="icon"></i>
            <span>{{ __('Settings') }}</span>
        </a>
    </nav>
</aside>
