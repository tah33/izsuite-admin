{{-- The business at the head of the receipt roll (Doc 18 Phases C4 and F3):
     its logo, names, address, location, phone and e-mail, registration and
     tax numbers, and the location's own header lines. --}}
<div class="center">
    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt="">
    @endif
    <div class="business">{{ $business['trading_name'] ?: $business['name'] }}</div>
    @if (($business['trading_name'] ?? null) && $business['trading_name'] !== $business['name'])
        <div class="muted">{{ $business['name'] }}</div>
    @endif
    @foreach ((array) ($business['address'] ?? []) as $line)
        <div class="muted">{{ $line }}</div>
    @endforeach
    @if ($business['location'] ?? null)
        <div class="muted">{{ $business['location'] }}</div>
    @endif
    @foreach (array_filter([$business['phone'] ?? null, $business['email'] ?? null]) as $line)
        <div class="muted">{{ $line }}</div>
    @endforeach
    @if ($business['registration_number'] ?? null)
        <div class="muted">{{ __('Registration No.', [], $lang) }}: {{ $business['registration_number'] }}</div>
    @endif
    @foreach ($business['tax_numbers'] ?? [] as $tax)
        <div class="muted">{{ $tax['name'] ?: __('Tax No.', [], $lang) }}: {{ $tax['number'] }}</div>
    @endforeach
    @foreach (array_filter(preg_split('/\R/', (string) ($business['location_header'] ?? ''))) as $line)
        <div class="muted">{{ $line }}</div>
    @endforeach
</div>
