{{-- An online order's shipment's packing slip (Doc 19 Phase G2), drawn with
     the order's invoice's template: the customer and where the parcel goes,
     its facts - the shipment, its day, the order's day and shipping method,
     the carrier and tracking - and how much of each product is in it. No
     prices, and never the business's own notes: it goes in the parcel
     (DocumentRenderer, PackingSlipSheet). --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('documents.partials.styles')
</head>
<body>
    <main class="sheet">
        @include('documents.partials.header')
        @include('documents.partials.parties')

        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('Item', [], $lang) }}</th>
                    @if ($skus)
                        <th>{{ __('SKU', [], $lang) }}</th>
                    @endif
                    <th class="num">{{ __('Qty', [], $lang) }}</th>
                    @if ($units)
                        <th>{{ __('Unit', [], $lang) }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            <div class="line-name">{{ $row['name'] }}</div>
                            @if ($row['options'] !== '')
                                <div class="line-note">{{ $row['options'] }}</div>
                            @endif
                        </td>
                        @if ($skus)
                            <td>{{ $row['sku'] }}</td>
                        @endif
                        <td class="num">{{ $row['quantity'] }}</td>
                        @if ($units)
                            <td>{{ $row['unit'] }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($part)
            <p class="notice">{{ __('This parcel holds part of order :number.', ['number' => $number], $lang) }}</p>
        @endif

        <div class="signature">
            <div class="muted">{{ __('Received By', [], $lang) }}</div>
        </div>
    </main>
</body>
</html>
