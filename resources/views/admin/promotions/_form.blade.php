{{-- A promotion code's fields, for adding and editing one (Doc 15 section 15.17). Lists left empty mean all. --}}
@php
    $type   = old('discount_type', $promotion->discount_type ?? \App\Models\Billing\Promotion::PERCENT);
    $picked = fn (string $list) => (array) (session()->hasOldInput() ? old($list, []) : ($promotion->{$list} ?? []));
    $hint   = 'text-xs mt-1 text-[var(--text-muted)]';
@endphp

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('Code and Discount') }}</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label" for="name">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $promotion->name ?? '') }}" required maxlength="100" placeholder="{{ __('e.g. Spring launch') }}">
            <p class="{{ $hint }}">{{ __('For the admin; a business sees the code.') }}</p>
        </div>

        <div>
            <label class="form-label" for="code">{{ __('Code') }}</label>
            <input type="text" name="code" id="code" class="form-input uppercase" value="{{ old('code', $promotion->code ?? '') }}" required minlength="3" maxlength="40" pattern="[A-Za-z0-9_\-]{3,40}" placeholder="{{ __('e.g. SPRING20') }}">
            <p class="{{ $hint }}">{{ __('What a business types when it subscribes, in any case: 3 to 40 letters, numbers, - or _.') }}</p>
        </div>

        <div>
            <label class="form-label" for="discount_value">{{ __('Discount') }}</label>
            <div class="flex gap-2">
                <select name="discount_type" class="form-select w-40 shrink-0" aria-label="{{ __('Discount type') }}">
                    <option value="percent" @selected($type === 'percent')>{{ __('Percent') }}</option>
                    <option value="fixed" @selected($type === 'fixed')>{{ __('Fixed amount') }}</option>
                </select>
                <input type="number" name="discount_value" id="discount_value" class="form-input" step="0.01" min="0.01" value="{{ old('discount_value', isset($promotion) ? $promotion->discount_value : '') }}" required placeholder="{{ __('e.g. 20') }}">
            </div>
            <p class="{{ $hint }}">{{ __('Off the first invoice\'s plan price, before any credit and tax. Renewals are at the full price.') }}</p>
        </div>

        <div>
            <label class="form-label" for="currency">{{ __('Currency') }}</label>
            <select name="currency" id="currency" class="form-select">
                <option value="">{{ __('None: a percent') }}</option>
                @foreach($currencies as $currency)
                    <option value="{{ $currency->code }}" @selected(old('currency', $promotion->currency ?? '') === $currency->code)>{{ $currency->name }} ({{ $currency->code }})</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('A fixed amount comes off invoices in this currency only.') }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-1">{{ __('Who Can Use It') }}</h3>
    <p class="text-sm mb-4 text-[var(--text-muted)]">{{ __('Nothing ticked or chosen in a list means all of it.') }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <span class="form-label">{{ __('Plans') }}</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($plans as $plan)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="packages[]" value="{{ $plan->key }}" class="form-checkbox" @checked(in_array($plan->key, $picked('packages'), true))>
                        <span class="text-sm">{{ $plan->name }}</span>
                    </label>
                @endforeach
            </div>

            <span class="form-label mt-4">{{ __('Billed') }}</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach(\App\Models\Billing\WorkspaceSubscription::CYCLES as $cycle)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="cycles[]" value="{{ $cycle }}" class="form-checkbox" @checked(in_array($cycle, $picked('cycles'), true))>
                        <span class="text-sm">{{ __(ucfirst($cycle)) }}</span>
                    </label>
                @endforeach
            </div>

            <label class="flex items-center gap-2 cursor-pointer mt-4">
                <input type="hidden" name="new_customers_only" value="0">
                <input type="checkbox" name="new_customers_only" value="1" class="form-checkbox" @checked(old('new_customers_only', $promotion->new_customers_only ?? false))>
                <span class="text-sm font-medium">{{ __('New customers only: businesses that never paid an invoice') }}</span>
            </label>
        </div>

        <div>
            <label class="form-label" for="countries">{{ __('Billing countries') }}</label>
            <select name="countries[]" id="countries" class="form-select" multiple data-placeholder="{{ __('Every country') }}">
                @foreach($countries as $country)
                    <option value="{{ $country['code'] }}" @selected(in_array($country['code'], $picked('countries'), true))>{{ $country['name'] }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('From the business\'s billing details. Type to find a country; choose as many as needed.') }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('When and How Often') }}</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="form-label" for="starts_on">{{ __('From') }}</label>
            <input type="date" name="starts_on" id="starts_on" class="form-input" value="{{ old('starts_on', $promotion?->starts_on?->toDateString()) }}">
            <p class="{{ $hint }}">{{ __('Empty: from now.') }}</p>
        </div>

        <div>
            <label class="form-label" for="ends_on">{{ __('Until') }}</label>
            <input type="date" name="ends_on" id="ends_on" class="form-input" value="{{ old('ends_on', $promotion?->ends_on?->toDateString()) }}">
            <p class="{{ $hint }}">{{ __('The last day it can be used. Empty: no end.') }}</p>
        </div>

        <div>
            <label class="form-label" for="status">{{ __('Status') }}</label>
            <select name="status" id="status" class="form-select">
                <option value="active" @selected(old('status', $promotion->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                <option value="paused" @selected(old('status', $promotion->status ?? 'active') === 'paused')>{{ __('Paused') }}</option>
            </select>
            <p class="{{ $hint }}">{{ __('Paused, the code takes nothing off until it is active again.') }}</p>
        </div>

        <div>
            <label class="form-label" for="max_redemptions">{{ __('Uses in all') }}</label>
            <input type="number" name="max_redemptions" id="max_redemptions" class="form-input" min="1" step="1" value="{{ old('max_redemptions', $promotion->max_redemptions ?? '') }}" placeholder="{{ __('No limit') }}">
            <p class="{{ $hint }}">{{ __('Each subscription it took something off counts, until its invoice is voided.') }}</p>
        </div>

        <div>
            <label class="form-label" for="per_business">{{ __('Uses per business') }}</label>
            <input type="number" name="per_business" id="per_business" class="form-input" min="1" step="1" value="{{ old('per_business', $promotion->per_business ?? 1) }}" required>
        </div>
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="btn btn-primary">
        <i data-lucide="save" class="w-4 h-4"></i>
        {{ isset($promotion) ? __('Update Promotion') : __('Add Promotion') }}
    </button>
    <a href="{{ route('admin.promotions.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
</div>
