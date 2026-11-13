<aside id="sidebar" class="sidebar fixed top-0 start-0 flex flex-col z-40">
    <div class="sidebar-logo">
        <a href="{{ route('admin.overview') }}" class="flex items-center justify-center gap-2.5">
            @if(setting('site_logo'))
                <x-site-logo class="w-7 h-7" />
                <span class="logo-text block leading-tight">{{ setting('site_name', config('brand.name')) }}</span>
            @else
                {{-- Full lockup, reversed: the sidebar is a dark surface. 40px keeps the
                     tagline legible and still leaves room inside the 64px header row. --}}
                <x-site-logo variant="full" tone="light" class="h-10 w-auto" />
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

        {{-- izSuite support's sessions in businesses, for review (Doc 15 Phase B4). --}}
        <a href="{{ route('admin.support-sessions.index') }}" class="sidebar-link {{ request()->routeIs('admin.support-sessions.*') ? 'active' : '' }}">
            <i data-lucide="life-buoy" class="icon"></i>
            <span>{{ __('Support Sessions') }}</span>
        </a>

        <div class="sidebar-section-title">{{ __('App System') }}</div>

        <a href="{{ route('admin.app-categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.app-categories.*') ? 'active' : '' }}">
            <i data-lucide="tags" class="icon"></i>
            <span>{{ __('App Categories') }}</span>
        </a>

        <a href="{{ route('admin.apps.index') }}" class="sidebar-link {{ request()->routeIs('admin.apps.*') ? 'active' : '' }}">
            <i data-lucide="app-window" class="icon"></i>
            <span>{{ __('Apps') }}</span>
        </a>

        <a href="{{ route('admin.packages.index') }}" class="sidebar-link {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}">
            <i data-lucide="layers-3" class="icon"></i>
            <span>{{ __('Packages') }}</span>
        </a>

        <a href="{{ route('admin.package-access.index') }}" class="sidebar-link {{ request()->routeIs('admin.package-access.*') ? 'active' : '' }}">
            <i data-lucide="grid-3x3" class="icon"></i>
            <span>{{ __('Package Access') }}</span>
        </a>

        <a href="{{ route('admin.subscription-invoices.index') }}" class="sidebar-link {{ request()->routeIs('admin.subscription-invoices.*') ? 'active' : '' }}">
            <i data-lucide="receipt" class="icon"></i>
            <span>{{ __('Subscription Invoices') }}</span>
        </a>

        <a href="{{ route('admin.payments.index') }}" class="sidebar-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
            <i data-lucide="banknote" class="icon"></i>
            <span>{{ __('Payments') }}</span>
        </a>

        <a href="{{ route('admin.refunds.index') }}" class="sidebar-link {{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}">
            <i data-lucide="undo-2" class="icon"></i>
            <span>{{ __('Refunds') }}</span>
        </a>

        <a href="{{ route('admin.subscription-taxes.index') }}" class="sidebar-link {{ request()->routeIs('admin.subscription-taxes.*') ? 'active' : '' }}">
            <i data-lucide="percent" class="icon"></i>
            <span>{{ __('Subscription Taxes') }}</span>
        </a>

        <a href="{{ route('admin.menu-items.index') }}" class="sidebar-link {{ request()->routeIs('admin.menu-items.*') ? 'active' : '' }}">
            <i data-lucide="panel-left" class="icon"></i>
            <span>{{ __('Menu Builder') }}</span>
        </a>

        <a href="{{ route('admin.businesses.index') }}" class="sidebar-link {{ request()->routeIs('admin.businesses.*', 'admin.workspaces.*') ? 'active' : '' }}">
            <i data-lucide="building-2" class="icon"></i>
            <span>{{ __('Businesses') }}</span>
        </a>

        <a href="{{ route('admin.clients.index') }}" class="sidebar-link {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
            <i data-lucide="users" class="icon"></i>
            <span>{{ __('Users') }}</span>
        </a>

        <div class="sidebar-section-title">{{ __('Management') }}</div>


        <a href="{{ route('admin.departments.index') }}" class="sidebar-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
            <i data-lucide="layers" class="icon"></i>
            <span>{{ __('Departments') }}</span>
        </a>

        {{-- Businesses' subscriptions to izSuite's plans (Doc 15 Phase C1); the earlier product's Plans & Pricing and Subscription History are gone. --}}
        <a href="{{ route('admin.subscriptions.index') }}" class="sidebar-link {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
            <i data-lucide="repeat" class="icon"></i>
            <span>{{ __('Subscriptions') }}</span>
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

        <a href="{{ route('admin.audit-log.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-log.*') ? 'active' : '' }}">
            <i data-lucide="scroll-text" class="icon"></i>
            <span>{{ __('Audit Log') }}</span>
        </a>
    </nav>
</aside>
