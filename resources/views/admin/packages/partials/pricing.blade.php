{{-- What the plan costs and gives (Doc 13 sections 2, 12 and 18): its prices, trial and grace, and whether it takes the branding off --}}
<div class="divider"></div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div>
        {{-- The currencies the admin keeps active (Admin > Currencies); one no longer active stays listed while the plan uses it. --}}
        @php($chosen = old('price_currency', $package->price_currency ?? 'USD'))
        <label class="form-label" for="price_currency">{{ __('Currency') }}</label>
        <select id="price_currency" name="price_currency" class="form-select" required>
            @unless($currencies->contains('code', $chosen))
                <option value="{{ $chosen }}" selected>{{ $chosen }}</option>
            @endunless
            @foreach($currencies as $currency)
                <option value="{{ $currency->code }}" @selected($chosen === $currency->code)>{{ $currency->name }} ({{ $currency->code }})</option>
            @endforeach
        </select>
        @error('price_currency')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="form-label" for="price_monthly">{{ __('Price a month') }}</label>
        <input type="number" id="price_monthly" name="price_monthly" min="0" step="0.01" value="{{ old('price_monthly', $package->price_monthly) }}" class="form-input" placeholder="{{ __('Not announced') }}">
        @error('price_monthly')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="form-label" for="price_yearly">{{ __('Price a year') }}</label>
        <input type="number" id="price_yearly" name="price_yearly" min="0" step="0.01" value="{{ old('price_yearly', $package->price_yearly) }}" class="form-input" placeholder="{{ __('Not announced') }}">
        @error('price_yearly')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>
<p class="text-xs -mt-2 mb-4 text-[var(--text-muted)]">{{ __('Shown on the Pricing page and in each business\'s Subscription. Leave a price empty while it is not announced; 0 is free.') }}</p>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label class="form-label" for="trial_days">{{ __('Trial (days)') }}</label>
        <input type="number" id="trial_days" name="trial_days" min="1" max="365" step="1" value="{{ old('trial_days', $package->trial_days) }}" class="form-input" placeholder="{{ __('No trial') }}">
        @error('trial_days')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="form-label" for="grace_days">{{ __('Grace after a period ends unpaid (days)') }}</label>
        <input type="number" id="grace_days" name="grace_days" min="1" max="90" step="1" value="{{ old('grace_days', $package->grace_days) }}" class="form-input" placeholder="{{ __('No grace period') }}">
        @error('grace_days')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<label class="flex items-center gap-3 cursor-pointer mb-1">
    <input type="checkbox" name="removes_branding" value="1" class="toggle-switch" @checked(old('removes_branding', $package->removes_branding))>
    <span class="form-label mb-0">{{ __('Takes "Powered by izSuite" off documents') }}</span>
</label>
<p class="text-xs mb-4 text-[var(--text-muted)]">{{ __('Documents issued before keep what they were printed with.') }}</p>

<div class="divider"></div>
