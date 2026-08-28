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
        
        $activeTab = $firstErrorGroup ?: request('tab', $groups[0] ?? 'general');
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
                            @php
                                $fieldValue = old('settings.' . $setting['key'], $setting['value']);
                                $fieldError = $errors->first('settings.' . $setting['key']);
                                $providerScope = null;

                                if (str_starts_with($setting['key'], 'ai_openai_')) {
                                    $providerScope = 'openai';
                                }

                                if (str_starts_with($setting['key'], 'ai_gemini_')) {
                                    $providerScope = 'gemini';
                                }
                            @endphp

                            <div class="form-group {{ $providerScope ? 'ai-provider-field' : '' }}" @if($providerScope) data-provider="{{ $providerScope }}" @endif>
                                <label for="setting_{{ $setting['key'] }}" class="form-label">
                                    {{ str_replace('_', ' ', ucfirst(str_replace($group . '_', '', $setting['key']))) }}
                                    @if(in_array($setting['key'], ['ai_active_provider', 'ai_temperature', 'ai_max_tokens'])) <span class="text-[var(--danger)]">*</span> @endif
                                </label>

                                @if($setting['key'] === 'default_currency')
                                    <select name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}">
                                        @foreach(\App\Models\Admin\Currency::active()->orderBy('name')->get() as $curr)
                                            <option value="{{ $curr->code }}" {{ $fieldValue === $curr->code ? 'selected' : '' }}>
                                                {{ $curr->name }} ({{ $curr->symbol }} / {{ $curr->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif($setting['key'] === 'default_language')
                                    <select name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}">
                                        @foreach(\App\Models\Admin\Language::active()->orderBy('name')->get() as $lang)
                                            <option value="{{ $lang->code }}" {{ $fieldValue === $lang->code ? 'selected' : '' }}>
                                                {{ $lang->name }} ({{ $lang->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif($setting['key'] === 'ai_active_provider')
                                    <select name="settings[{{ $setting['key'] }}]" id="setting_ai_active_provider" class="form-input {{ $fieldError ? 'form-input-error' : '' }}">
                                        <option value="openai" {{ $fieldValue === 'openai' ? 'selected' : '' }}>OpenAI</option>
                                        <option value="gemini" {{ $fieldValue === 'gemini' ? 'selected' : '' }}>Gemini</option>
                                    </select>
                                @elseif(str_ends_with($setting['key'], '_enabled'))
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="hidden" name="settings[{{ $setting['key'] }}]" value="0">
                                        <input type="checkbox" name="settings[{{ $setting['key'] }}]" value="1" class="w-4 h-4 rounded ai-enabled-input" data-key="{{ $setting['key'] }}" {{ (string) $fieldValue === '1' ? 'checked' : '' }}>
                                        <span class="text-sm font-medium text-[var(--text-primary)]">{{ __('Enabled') }}</span>
                                    </label>
                                @elseif(str_contains($setting['key'], 'api_key') || str_contains($setting['key'], 'client_secret'))
                                    <input
                                        type="password"
                                        name="settings[{{ $setting['key'] }}]"
                                        id="setting_{{ $setting['key'] }}"
                                        class="form-input {{ $fieldError ? 'form-input-error' : '' }} {{ str_contains($setting['key'], 'ai_') ? 'ai-conditional-input' : '' }}"
                                        data-key="{{ $setting['key'] }}"
                                        value="{{ $fieldValue }}"
                                        autocomplete="off"
                                    >
                                @elseif(in_array($setting['key'], ['ai_openai_model', 'ai_gemini_model']))
                                    <input
                                        type="text"
                                        name="settings[{{ $setting['key'] }}]"
                                        id="setting_{{ $setting['key'] }}"
                                        class="form-input {{ $fieldError ? 'form-input-error' : '' }} ai-conditional-input"
                                        data-key="{{ $setting['key'] }}"
                                        value="{{ $fieldValue }}"
                                    >
                                @elseif(in_array($setting['key'], ['ai_temperature', 'ai_max_tokens']))
                                    <input
                                        type="text"
                                        name="settings[{{ $setting['key'] }}]"
                                        id="setting_{{ $setting['key'] }}"
                                        class="form-input {{ $fieldError ? 'form-input-error' : '' }}"
                                        value="{{ $fieldValue }}"
                                    >
                                @elseif($setting['key'] === 'site_logo')
                                    <div class="flex items-center gap-4">
                                        @if($fieldValue)
                                            <div class="w-16 h-16 rounded border bg-gray-50 flex items-center justify-center overflow-hidden">
                                                <img src="{{ app(\App\Services\Support\ImageService::class)->publicUrl($fieldValue) }}" class="max-w-full max-h-full object-contain">
                                            </div>
                                        @else
                                            <div class="w-16 h-16 rounded border bg-gray-50 flex items-center justify-center text-gray-400">
                                                 <x-site-logo class="w-8 h-8" />
                                            </div>
                                        @endif
                                        <div class="flex-1">
                                            <input type="file" name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}" accept="image/*">
                                            <p class="text-xs text-[var(--text-muted)] mt-1">{{ __('Recommended size: 200x200px. Max: 2MB.') }}</p>
                                        </div>
                                    </div>
                                @elseif($setting['key'] === 'site_favicon')
                                    <div class="flex items-center gap-4">
                                        @if($fieldValue)
                                            <div class="w-10 h-10 rounded border bg-gray-50 flex items-center justify-center overflow-hidden">
                                                <img src="{{ app(\App\Services\Support\ImageService::class)->publicUrl($fieldValue) }}" class="max-w-full max-h-full object-contain">
                                            </div>
                                        @else
                                            <div class="w-10 h-10 rounded border bg-gray-50 flex items-center justify-center text-gray-400">
                                                <i data-lucide="image" class="w-5 h-5"></i>
                                            </div>
                                        @endif
                                        <div class="flex-1">
                                            <input type="file" name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}" accept="image/*,.ico">
                                            <p class="text-xs text-[var(--text-muted)] mt-1">{{ __('Recommended: 32×32px PNG or ICO.') }}</p>
                                        </div>
                                    </div>
                                @elseif($setting['key'] === 'primary_color')
                                    <div class="flex items-center gap-3">
                                        <input type="color" name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="w-10 h-10 rounded-lg p-1 bg-white border border-[var(--card-border)] cursor-pointer" value="{{ $fieldValue }}">
                                        <input type="text" value="{{ $fieldValue }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }} flex-1 font-mono text-sm" placeholder="#000000" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                                    </div>
                                @elseif(str_contains($setting['key'], 'description') || str_contains($setting['key'], 'address') || $setting['key'] === 'footer_text')
                                    <textarea
                                        name="settings[{{ $setting['key'] }}]"
                                        id="setting_{{ $setting['key'] }}"
                                        class="form-input {{ $fieldError ? 'form-input-error' : '' }}"
                                        rows="3"
                                    >{{ $fieldValue }}</textarea>
                                @else
                                    <input
                                        type="text"
                                        name="settings[{{ $setting['key'] }}]"
                                        id="setting_{{ $setting['key'] }}"
                                        class="form-input {{ $fieldError ? 'form-input-error' : '' }}"
                                        value="{{ $fieldValue }}"
                                    >
                                @endif

                                @if($fieldError)
                                    <p class="form-error" style="color: #ef4444; display: flex; align-items: center; gap: 4px;">
                                        <i data-lucide="alert-circle" class="w-3.5 h-3.5" style="color: #ef4444; margin: 0;"></i>
                                        <span style="font-size: 0.7rem; font-weight: 500;">{{ $fieldError }}</span>
                                    </p>
                                @endif
                            </div>
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

    document.getElementById('setting_ai_active_provider')?.addEventListener('change', syncAiSettingsVisibility);
    syncAiSettingsVisibility();
</script>
@endpush

