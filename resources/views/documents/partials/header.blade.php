{{-- The business - logo, name, trading name, address, contacts, registration
     and tax numbers, and the lines its location adds - and what the document
     is, laid out as its template's header layout says. Registration and tax
     numbers always show: they are not the business's to hide (Doc 06 section
     14). --}}
<header class="head head--{{ $template['header_layout'] }}">
    <div>
        @if ($logo)
            <img class="logo" src="{{ $logo }}" alt="">
        @endif
        <div class="business-name">{{ $business['name'] }}</div>
        @if ($business['trading_name'] ?? null)
            <div class="muted">{{ __('Trading as', [], $lang) }} {{ $business['trading_name'] }}</div>
        @endif
        @foreach ($business['address'] ?? [] as $line)
            <div class="muted">{{ $line }}</div>
        @endforeach
        @if (in_array('business_contact', $template['visible_fields'], true))
            @foreach (array_filter([$business['phone'] ?? null, $business['email'] ?? null, $business['website'] ?? null]) as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
        @endif
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
    <div class="title">
        <h1>{{ __($label, [], $lang) }}</h1>
        <div class="number">{{ $number ?? __('Draft', [], $lang) }}</div>
    </div>
</header>

@if ($stamp)
    <div class="stamp stamp--{{ strtolower($stamp) }}">{{ __($stamp, [], $lang) }}</div>
@endif
