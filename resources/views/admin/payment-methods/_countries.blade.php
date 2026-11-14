{{-- The billing countries a way to pay is offered in (Doc 15 Phase F3b): none chosen, every country. Expects $method (null for a new one) and $hint. --}}
@php $chosen = old('countries', $method?->countries ?? []); @endphp
<div class="form-group">
    <label for="countries" class="form-label">{{ __('Offered in') }}</label>
    <select id="countries" name="countries[]" class="form-select" multiple data-placeholder="{{ __('Every country') }}">
        @foreach (\App\Support\Countries::all() as $country)
            <option value="{{ $country['code'] }}" @selected(in_array($country['code'], $chosen, true))>{{ $country['name'] }}</option>
        @endforeach
    </select>
    @error('countries') <span class="form-error">{{ $message }}</span> @enderror
    <p class="text-xs mt-1 text-[var(--text-muted)]">{{ $hint }}</p>
</div>
