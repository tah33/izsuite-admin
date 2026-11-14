{{-- A plan's reporting and support levels, the cycles it is offered on and the billing countries it is offered in (Doc 15 section 15.8 - Phase E3a). Billing Starter is offered everywhere. --}}
@php
    $levels    = [
        'reporting_level' => [__('Reports'), \App\Models\Billing\Package::REPORTING_LEVELS],
        'support_level'   => [__('Support'), \App\Models\Billing\Package::SUPPORT_LEVELS],
    ];
    $countries = (array) (session()->hasOldInput() ? old('countries', []) : ($package->countries ?? []));
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-1">
    @foreach ($levels as $field => [$label, $options])
        <div>
            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
            <select id="{{ $field }}" name="{{ $field }}" class="form-select">
                <option value="">{{ __('Not shown') }}</option>
                @foreach ($options as $value => $name)
                    <option value="{{ $value }}" @selected(old($field, $package->{$field}) === $value)>{{ $name }}</option>
                @endforeach
            </select>
            @error($field)
                <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>
<p class="text-xs mb-4 text-[var(--text-muted)]">{{ __('Shown with the plan on the Pricing page and as businesses compare plans.') }}</p>

@unless ($package->isFree())
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <span class="form-label">{{ __('Paid') }}</span>
            @foreach (['offers_monthly' => __('Monthly'), 'offers_yearly' => __('Yearly')] as $field => $label)
                {{-- Sent as 0 when unticked, so the plan is not offered on it. --}}
                <input type="hidden" name="{{ $field }}" value="0">
                <label class="flex items-center gap-3 cursor-pointer mb-2">
                    <input type="checkbox" name="{{ $field }}" value="1" class="toggle-switch" @checked(old($field, $package->{$field} ?? true))>
                    <span class="form-label mb-0">{{ $label }}</span>
                </label>
            @endforeach
            @error('offers_monthly')
                <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
            @enderror
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Subscribers already paying on a cycle no longer offered renew on it.') }}</p>
        </div>
        <div>
            <label class="form-label" for="countries">{{ __('Offered in') }}</label>
            <select id="countries" name="countries[]" class="form-select" multiple data-placeholder="{{ __('Every country') }}">
                @foreach (\App\Support\Countries::all() as $country)
                    <option value="{{ $country['code'] }}" @selected(in_array($country['code'], $countries, true))>{{ $country['name'] }}</option>
                @endforeach
            </select>
            @error('countries')
                <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
            @enderror
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('By the business\'s billing country. Businesses on it elsewhere keep it.') }}</p>
        </div>
    </div>
@endunless

<div class="divider"></div>
