{{-- A subscription tax rule's fields, for adding and editing one (Doc 15 section 16). --}}
<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('Tax') }}</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label" for="country">{{ __('Country') }}</label>
            <select name="country" id="country" class="form-select" required>
                <option value="">{{ __('Choose a country') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country['code'] }}" @selected(old('country', $tax->country ?? '') === $country['code'])>{{ $country['name'] }}</option>
                @endforeach
            </select>
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('From the business\'s billing details.') }}</p>
        </div>

        <div>
            <label class="form-label" for="name">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $tax->name ?? '') }}" required maxlength="50" placeholder="{{ __('e.g. VAT') }}">
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('As the invoice shows it.') }}</p>
        </div>

        <div>
            <label class="form-label" for="rate">{{ __('Rate (%)') }}</label>
            <input type="number" name="rate" id="rate" class="form-input" step="0.001" min="0" max="100" value="{{ old('rate', isset($tax) ? $tax->percent() : '') }}" required placeholder="{{ __('e.g. 15') }}">
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Added on top of the plan price, after any upgrade credit.') }}</p>
        </div>

        <div>
            <label class="form-label" for="starts_on">{{ __('From') }}</label>
            <input type="date" name="starts_on" id="starts_on" class="form-input" value="{{ old('starts_on', isset($tax) ? $tax->starts_on->toDateString() : today()->toDateString()) }}" required>
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Invoices issued from this day carry it. A later rule for the country takes over from its own day.') }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('Buyers with a Tax ID') }}</h3>

    <label class="flex items-center gap-2 cursor-pointer mb-3">
        <input type="hidden" name="reverse_charge" value="0">
        <input type="checkbox" name="reverse_charge" value="1" class="form-checkbox" {{ old('reverse_charge', $tax->reverse_charge ?? false) ? 'checked' : '' }}>
        <span class="text-sm font-medium">{{ __('Reverse charge: no tax when the business gives its tax ID') }}</span>
    </label>

    <div>
        <label class="form-label" for="reverse_charge_note">{{ __('Note on the invoice') }}</label>
        <input type="text" name="reverse_charge_note" id="reverse_charge_note" class="form-input" maxlength="255" value="{{ old('reverse_charge_note', $tax->reverse_charge_note ?? '') }}" placeholder="{{ \App\Models\Billing\SubscriptionTax::REVERSE_CHARGE_NOTE }}">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Printed in place of the tax with a reverse charge. Left empty, the invoice prints the text shown.') }}</p>
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="btn btn-primary">
        <i data-lucide="save" class="w-4 h-4"></i>
        {{ isset($tax) ? __('Update Tax') : __('Add Tax') }}
    </button>
    <a href="{{ route('admin.subscription-taxes.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
</div>
