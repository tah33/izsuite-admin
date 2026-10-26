{{-- Settings: the input for one setting, by its key --}}
@if($setting['key'] === 'default_currency')
    <select name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}">
        @foreach($currencyOptions as $curr)
            <option value="{{ $curr->code }}" {{ $fieldValue === $curr->code ? 'selected' : '' }}>
                {{ $curr->name }} ({{ $curr->symbol }} / {{ $curr->code }})
            </option>
        @endforeach
    </select>
@elseif($setting['key'] === 'default_language')
    <select name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}">
        @foreach($languageOptions as $lang)
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
@elseif($setting['key'] === 'smtp_encryption')
    <select name="settings[{{ $setting['key'] }}]" id="setting_{{ $setting['key'] }}" class="form-input {{ $fieldError ? 'form-input-error' : '' }}">
        <option value="tls" {{ $fieldValue === 'tls' ? 'selected' : '' }}>{{ __('TLS / STARTTLS') }}</option>
        <option value="ssl" {{ $fieldValue === 'ssl' ? 'selected' : '' }}>{{ __('SSL') }}</option>
        <option value="none" {{ $fieldValue === 'none' ? 'selected' : '' }}>{{ __('None') }}</option>
    </select>
@elseif($setting['key'] === 'smtp_password')
    {{-- Rendered empty on purpose so the stored password never reaches the browser. --}}
    <input
        type="password"
        name="settings[{{ $setting['key'] }}]"
        id="setting_{{ $setting['key'] }}"
        class="form-input {{ $fieldError ? 'form-input-error' : '' }}"
        value=""
        placeholder="{{ $setting['value'] ? str_repeat(chr(0xE2).chr(0x80).chr(0xA2), 8).'  '.__('saved') : '' }}"
        autocomplete="new-password"
    >
@elseif($setting['key'] === 'smtp_port')
    <input
        type="number"
        min="1"
        max="65535"
        name="settings[{{ $setting['key'] }}]"
        id="setting_{{ $setting['key'] }}"
        class="form-input {{ $fieldError ? 'form-input-error' : '' }}"
        value="{{ $fieldValue }}"
    >
@elseif($setting['key'] === 'smtp_from_address')
    <input
        type="email"
        name="settings[{{ $setting['key'] }}]"
        id="setting_{{ $setting['key'] }}"
        class="form-input {{ $fieldError ? 'form-input-error' : '' }}"
        value="{{ $fieldValue }}"
        placeholder="no-reply@example.com"
    >
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
