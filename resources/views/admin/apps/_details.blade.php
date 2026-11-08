{{-- The app's own page in the Apps Center (Doc 13 section 6): what it does in full, its main features and where to get help --}}
<div class="mb-4">
    <label class="form-label" for="long_description">{{ __('Full Description') }}</label>
    <textarea id="long_description" name="long_description" rows="4" class="form-input" placeholder="{{ __('What the app does for a business, shown on its page in the Apps Center.') }}">{{ old('long_description', $app->long_description) }}</textarea>
    @error('long_description')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="mb-4">
    <label class="form-label" for="features_text">{{ __('Main Features') }}</label>
    <textarea id="features_text" name="features_text" rows="5" class="form-input" placeholder="{{ __('One feature a line') }}">{{ old('features_text', implode("\n", $app->features ?? [])) }}</textarea>
    <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('One a line, up to 12. Listed on the app\'s page.') }}</p>
    @error('features')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
    @error('features.*')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="mb-4">
    <label class="form-label" for="support_url">{{ __('Support Link') }}</label>
    <input id="support_url" name="support_url" type="url" class="form-input" value="{{ old('support_url', $app->support_url) }}" placeholder="https://">
    @error('support_url')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>
