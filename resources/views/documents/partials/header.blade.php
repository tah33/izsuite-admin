{{-- The business - logo, name, address, tax numbers - and what the document
     is, laid out as its template's header layout says. Tax numbers always
     show: they are not the business's to hide (Doc 06 section 14). --}}
<header class="head head--{{ $template['header_layout'] }}">
    <div>
        @if ($logo)
            <img class="logo" src="{{ $logo }}" alt="">
        @endif
        <div class="business-name">{{ $business['name'] }}</div>
        @foreach ($business['address'] ?? [] as $line)
            <div class="muted">{{ $line }}</div>
        @endforeach
        @if (in_array('business_contact', $template['visible_fields'], true))
            @foreach (array_filter([$business['phone'] ?? null, $business['website'] ?? null]) as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
        @endif
        @foreach ($business['tax_numbers'] ?? [] as $tax)
            <div class="muted">{{ $tax['name'] ?: __('Tax No.', [], $lang) }}: {{ $tax['number'] }}</div>
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
