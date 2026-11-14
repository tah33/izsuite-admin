{{-- The admin sidebar's Compliance and System sections (Doc 15): privacy, holds and retention, then the platform's own settings, audit and health. Included by components/admin-sidebar. --}}
        <div class="sidebar-section-title">{{ __('Compliance') }}</div>

        <a href="{{ route('admin.privacy-requests.index') }}" class="sidebar-link {{ request()->routeIs('admin.privacy-requests.*') ? 'active' : '' }}">
            <i data-lucide="shield-check" class="icon"></i>
            <span>{{ __('Privacy Requests') }}</span>
        </a>

        <a href="{{ route('admin.legal-holds.index') }}" class="sidebar-link {{ request()->routeIs('admin.legal-holds.*') ? 'active' : '' }}">
            <i data-lucide="lock-keyhole" class="icon"></i>
            <span>{{ __('Legal Holds') }}</span>
        </a>

        <a href="{{ route('admin.retention.index') }}" class="sidebar-link {{ request()->routeIs('admin.retention.*') ? 'active' : '' }}">
            <i data-lucide="archive" class="icon"></i>
            <span>{{ __('Data Retention') }}</span>
        </a>

        <div class="sidebar-section-title">{{ __('System') }}</div>

        <a href="{{ route('admin.countries.index') }}" class="sidebar-link {{ request()->routeIs('admin.countries.*') ? 'active' : '' }}">
            <i data-lucide="map" class="icon"></i>
            <span>{{ __('Countries') }}</span>
        </a>

        <a href="{{ route('admin.languages.index') }}" class="sidebar-link {{ request()->routeIs('admin.languages.*') ? 'active' : '' }}">
            <i data-lucide="globe" class="icon"></i>
            <span>{{ __('Languages') }}</span>
        </a>

        <a href="{{ route('admin.email-templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.email-templates.*') ? 'active' : '' }}">
            <i data-lucide="mail" class="icon"></i>
            <span>{{ __('E-mail Templates') }}</span>
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

        <a href="{{ route('admin.service-health.index') }}" class="sidebar-link {{ request()->routeIs('admin.service-health.*') ? 'active' : '' }}">
            <i data-lucide="activity" class="icon"></i>
            <span>{{ __('Service Health') }}</span>
        </a>

        <a href="{{ route('admin.incidents.index') }}" class="sidebar-link {{ request()->routeIs('admin.incidents.*') ? 'active' : '' }}">
            <i data-lucide="siren" class="icon"></i>
            <span>{{ __('Incidents') }}</span>
        </a>
