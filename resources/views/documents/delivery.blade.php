{{-- A sales order's delivery note (Doc 16 Phase C), drawn with the order's
     template: who it goes to and where, its facts - the order, the carrier,
     tracking number, vehicle or driver - how much of each line goes out, and
     who received it. No prices: a delivery is not a sale (DocumentRenderer). --}}
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
                    <th class="num">{{ __('Qty', [], $lang) }}</th>
                    <th>{{ __('Unit', [], $lang) }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        <td class="num">{{ $row['quantity'] }}</td>
                        <td>{{ $row['unit'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($notes)
            <p class="notice">{{ $notes }}</p>
        @endif

        <div class="signature">
            @if ($recipient)
                <div><strong>{{ $recipient }}</strong></div>
            @endif
            <div class="muted">{{ __('Received By', [], $lang) }}</div>
        </div>
    </main>
</body>
</html>
