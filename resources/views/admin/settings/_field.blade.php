{{-- Settings: one setting's label, input, help text and error --}}
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
        {{ $labels[$setting['key']] ?? str_replace('_', ' ', ucfirst(str_replace($group . '_', '', $setting['key']))) }}
        @if(in_array($setting['key'], $requiredKeys)) <span class="text-[var(--danger)]">*</span> @endif
    </label>

    @include('admin.settings._field-input')

    @if(isset($helpTexts[$setting['key']]))
        <p class="text-xs text-[var(--text-muted)] mt-1">{{ $helpTexts[$setting['key']] }}</p>
    @endif

    @if($fieldError)
        <p class="form-error" style="color: #ef4444; display: flex; align-items: center; gap: 4px;">
            <i data-lucide="alert-circle" class="w-3.5 h-3.5" style="color: #ef4444; margin: 0;"></i>
            <span style="font-size: 0.7rem; font-weight: 500;">{{ $fieldError }}</span>
        </p>
    @endif
</div>
