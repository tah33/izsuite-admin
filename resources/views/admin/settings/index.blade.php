@extends('layouts.admin')
@section('title', __('Settings'))

@section('content')

    {{-- Flash --}}
    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ __('Please fix the highlighted settings and try again.') }}
        </div>
    @endif

    {{-- Tabs --}}
    @php
        $groups = array_keys($grouped);
        
        // Find the first group that has an error
        $firstErrorGroup = null;
        foreach($grouped as $group => $settings) {
            foreach($settings as $setting) {
                if ($errors->has('settings.' . $setting['key'])) {
                    $firstErrorGroup = $group;
                    break 2;
                }
            }
        }
        
        $activeTab = $firstErrorGroup ?: request('tab', session('active_tab', $groups[0] ?? 'general'));

        // Auto-generated labels read badly for the SMTP keys ("Smtp from address"),
        // so anything listed here overrides the humanised key.
        $labels = [
            'smtp_enabled'      => __('Use custom SMTP'),
            'smtp_host'         => __('SMTP host'),
            'smtp_port'         => __('SMTP port'),
            'smtp_encryption'   => __('Encryption'),
            'smtp_username'     => __('SMTP username'),
            'smtp_password'     => __('SMTP password'),
            'smtp_from_address' => __('From address'),
            'smtp_from_name'    => __('From name'),
        ];

        $helpTexts = [
            'smtp_enabled'      => __('When off, the app falls back to the mailer configured in .env. Your credentials below are kept either way.'),
            'smtp_host'         => __('e.g. smtp.gmail.com, smtp.mailgun.org, smtp-relay.brevo.com'),
            'smtp_port'         => __('587 for TLS, 465 for SSL, 25 for unencrypted.'),
            'smtp_username'     => __('Usually the full email address of the sending account.'),
            'smtp_password'     => __('Leave blank to keep the saved password. Gmail requires an App Password, not your account password.'),
            'smtp_from_address' => __('The address recipients will see in the From field.'),
            'smtp_from_name'    => __('The name recipients will see, e.g. your site name.'),
        ];

        $requiredKeys = ['ai_active_provider', 'ai_temperature', 'ai_max_tokens', 'smtp_host', 'smtp_port', 'smtp_from_address'];
    @endphp

    <div class="flex items-center gap-1 mb-4 border-b border-[var(--card-border)] overflow-x-auto">
        @foreach($grouped as $group => $settings)
            @php
                $hasError = false;
                foreach($settings as $setting) {
                    if ($errors->has('settings.' . $setting['key'])) {
                        $hasError = true;
                        break;
                    }
                }
            @endphp
            <button type="button"
                class="settings-tab relative px-4 py-2 text-sm font-medium rounded-t-lg flex items-center gap-2 whitespace-nowrap {{ $group === $activeTab ? 'active' : '' }}"
                data-tab="{{ $group }}">
                {{ str_replace('_', ' ', ucfirst($group)) }}
                @if($hasError)
                    <span class="w-2 h-2 rounded-full bg-[var(--danger)] shadow-[0_0_8px_var(--danger)]"></span>
                @endif
            </button>
        @endforeach
    </div>

        @foreach($grouped as $group => $settings)
            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="_group" value="{{ $group }}">

                <div class="settings-panel card p-6 mb-4 {{ $group !== $activeTab ? 'hidden' : '' }}" data-tab="{{ $group }}">
                    <h2 class="text-lg font-semibold mb-4 text-[var(--text-primary)]">{{ str_replace('_', ' ', ucfirst($group)) }} {{ __('Settings') }}</h2>

                    <div class="space-y-4">
                        @foreach($settings as $setting)
                            @include('admin.settings._field')
                        @endforeach
                    </div>

                    <div class="mt-8 pt-6 border-t border-[var(--card-border)]">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            {{ __('Save') }} {{ str_replace('_', ' ', ucfirst($group)) }} {{ __('Settings') }}
                        </button>
                    </div>
                </div>
            </form>

            {{-- Sits outside the settings form on purpose: nesting forms is invalid HTML.
                 The panel toggle keys off data-tab, so both panels show together. --}}
            @if($group === 'mail')
                @include('admin.settings._test-mail')
            @endif
        @endforeach
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const group = tab.dataset.tab;

            document.querySelectorAll('.settings-tab').forEach(t => t.classList.toggle('active', t === tab));
            document.querySelectorAll('.settings-panel').forEach(panel => {
                panel.classList.toggle('hidden', panel.dataset.tab !== group);
            });

            const url = new URL(window.location);
            url.searchParams.set('tab', group);
            history.replaceState(null, '', url);
        });
    });

    function syncAiSettingsVisibility() {
        const providerSelect = document.getElementById('setting_ai_active_provider');
        if (!providerSelect) return;

        const activeProvider = providerSelect.value;

        document.querySelectorAll('.ai-provider-field').forEach(field => {
            const provider = field.dataset.provider;
            const isVisible = provider === activeProvider;
            field.classList.toggle('hidden', !isVisible);
        });
    }

    // jQuery, not addEventListener: the select is a Select2, which announces a
    // pick through jQuery.
    $('#setting_ai_active_provider').on('change', syncAiSettingsVisibility);
    syncAiSettingsVisibility();
</script>
@endpush

