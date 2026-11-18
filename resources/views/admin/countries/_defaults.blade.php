{{-- The defaults a new business in the country takes (Doc 15 section 15.19 - Phase F3a): its currency, time zone and regional settings, and its addresses' labels and format. Expects $country, $currencies, $zones and $example. --}}
@php
    $hint     = 'text-xs mt-1 text-[var(--text-muted)]';
    $value    = fn (string $field) => old($field, $country->{$field});
    $numbers  = \App\Models\User\DocumentTemplate::NUMBER_FORMAT_EXAMPLES;
    $listed   = $currencies->pluck('code')->all();
    $selects  = [
        'date_format'   => [__('Date Format'), collect(\App\Models\User\DocumentTemplate::DATE_FORMATS)->mapWithKeys(fn ($format) => [$format => now()->format($format)])->all()],
        'time_format'   => [__('Time Format'), ['24h' => __('24-hour (14:30)'), '12h' => __('12-hour (2:30 PM)')]],
        'number_format' => [__('Number Format'), $numbers],
        'week_start'    => [__('First Day of Week'), ['monday' => __('Monday'), 'sunday' => __('Sunday'), 'saturday' => __('Saturday')]],
        'paper_size'    => [__('Paper Size'), ['a4' => 'A4', 'letter' => __('Letter')]],
        'fiscal_year_start_month' => [__('Fiscal Year Starts'), collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => \Carbon\Carbon::create(2026, $month, 1)->format('F')])->all()],
    ];
@endphp
<div class="divider"></div>

<h2 class="section-title mb-1">{{ __('Defaults for New Businesses') }}</h2>
<p class="text-xs mb-4 text-[var(--text-muted)]">{{ __('What a business set up in :country starts from, and what Settings recommends it. A change is the country\'s next version, from the day below.', ['country' => $country->name]) }}</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="form-label" for="currency">{{ __('Currency') }}</label>
        <select id="currency" name="currency" class="form-select">
            <option value="">{{ __('None') }}</option>
            @if($value('currency') && ! in_array($value('currency'), $listed, true))
                <option value="{{ $value('currency') }}" selected>{{ $value('currency') }} · {{ __('not on izSuite\'s list') }}</option>
            @endif
            @foreach($currencies as $currency)
                <option value="{{ $currency->code }}" @selected($value('currency') === $currency->code)>{{ $currency->code }} · {{ $currency->name }}{{ $currency->is_active ? '' : ' ('.__('off').')' }}</option>
            @endforeach
        </select>
        <p class="{{ $hint }}">{{ __('The base currency the setup offers, while it is on under Currencies.') }}</p>
    </div>
    <div class="md:col-span-2">
        <label class="form-label" for="timezone">{{ __('Time Zone') }}</label>
        <select id="timezone" name="timezone" class="form-select">
            <option value="">{{ __('None: the owner\'s own') }}</option>
            @foreach($zones as $zone)
                <option value="{{ $zone }}" @selected($value('timezone') === $zone)>{{ $zone }}</option>
            @endforeach
        </select>
        <p class="{{ $hint }}">{{ __('The setup takes it when the owner\'s own time zone is not one of the country\'s.') }}</p>
    </div>
    @foreach($selects as $field => [$label, $options])
        <div>
            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
            <select id="{{ $field }}" name="{{ $field }}" class="form-select" required>
                @foreach($options as $option => $shown)
                    <option value="{{ $option }}" @selected((string) $value($field) === (string) $option)>{{ $shown }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>

<h3 class="text-sm font-semibold mt-6 mb-3 text-[var(--text-primary)]">{{ __('Addresses') }}</h3>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="form-label" for="region_label">{{ __('The Region Is Called') }}</label>
        <input type="text" id="region_label" name="region_label" class="form-input" maxlength="40" required value="{{ $value('region_label') }}">
    </div>
    <div>
        <label class="form-label" for="postal_label">{{ __('The Postal Code Is Called') }}</label>
        <input type="text" id="postal_label" name="postal_label" class="form-input" maxlength="40" required value="{{ $value('postal_label') }}">
    </div>
    <div class="hidden md:block"></div>
    <div class="md:col-span-2">
        <label class="form-label" for="address_format">{{ __('Address Format') }}</label>
        <textarea id="address_format" name="address_format" rows="6" class="form-input font-mono text-xs" maxlength="255" required>{{ $value('address_format') }}</textarea>
        <p class="{{ $hint }}">{{ __('One line of the address per line, each naming its parts: {line1}, {line2}, {city}, {region}, {postal}, {country}. A part left empty takes its comma with it; a line with none goes. Documents print addresses in their country\'s format.') }}</p>
    </div>
    <div>
        <span class="form-label">{{ __('Printed Like This') }}</span>
        <div class="text-sm rounded-lg p-3 bg-[var(--bg-secondary)] text-[var(--text-secondary)]">
            @foreach($example as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
        <p class="{{ $hint }}">{{ __('A sample address, in the saved format.') }}</p>
    </div>
</div>
