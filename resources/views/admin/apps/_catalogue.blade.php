{{-- An app's release, where it is offered and what it cannot be on beside (Doc 15 section 15.10 - Phase E2). Expects $app and $families (family key => name). --}}
@php
    $picked = fn (string $list) => (array) (session()->hasOldInput() ? old($list, []) : ($app->{$list} ?? []));
@endphp
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <label class="form-label" for="release_version">{{ __('Release version') }}</label>
        <input type="text" id="release_version" name="release_version" class="form-input" maxlength="20" value="{{ old('release_version', $app->release_version) }}" placeholder="1.0.0">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Shown in the Apps Center.') }}</p>
    </div>
    <div>
        <label class="form-label" for="countries">{{ __('Offered in') }}</label>
        <select id="countries" name="countries[]" class="form-select" multiple data-placeholder="{{ __('Every country') }}">
            @foreach(\App\Support\Countries::all() as $country)
                <option value="{{ $country['code'] }}" @selected(in_array($country['code'], $picked('countries'), true))>{{ $country['name'] }}</option>
            @endforeach
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('The business\'s country, from its profile. Those with it on elsewhere keep it.') }}</p>
    </div>
    <div>
        <label class="form-label" for="conflicts">{{ __('Cannot be on beside') }}</label>
        <select id="conflicts" name="conflicts[]" class="form-select" multiple data-placeholder="{{ __('No other app') }}">
            @foreach($families as $familyKey => $familyName)
                @continue($familyKey === $app->family_key)
                <option value="{{ $familyKey }}" @selected(in_array($familyKey, $picked('conflicts'), true))>{{ $familyName }}</option>
            @endforeach
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Either way round: neither turns on while the other is on.') }}</p>
    </div>
</div>
