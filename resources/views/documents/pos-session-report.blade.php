{{-- A POS session's X or Z report (Doc 18 sections 18.27 and 18.28 - Phase
     F3), on the receipt's 80 mm roll: the business and the location, the
     session - its register and cashier, when it opened, and when it closed
     or when the X report was made - what it took (its sales, taxes and the
     payments by method, and those waiting to be confirmed), and its cash: the
     opening cash, cash sales and refunds, cash in and out, and the cash
     expected - for a Z report, the cash counted and the variance with why.
     An X report closes nothing. --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('documents.partials.pos-roll-styles')
</head>
<body>
<main>
    @include('documents.partials.pos-roll-business')

    <div class="center business" style="margin-top: 6px">{{ __($heading, [], $lang) }}</div>

    <div class="rule"></div>

    <table>
        <tr><td>{{ __('Session', [], $lang) }}</td><td class="num"><strong>{{ $number }}</strong></td></tr>
        <tr><td>{{ __('Register', [], $lang) }}</td><td class="num">{{ $register }}</td></tr>
        @if ($cashier)
            <tr><td>{{ __('Cashier', [], $lang) }}</td><td class="num">{{ $cashier }}</td></tr>
        @endif
        <tr><td>{{ __('Opened', [], $lang) }}</td><td class="num">{{ $opened }}</td></tr>
        <tr><td>{{ __($closed ? 'Closed' : 'As at', [], $lang) }}</td><td class="num">{{ $closed ?? $at }}</td></tr>
    </table>

    <div class="rule"></div>

    <table>
        <tr><td>{{ __('Sales', [], $lang) }} ({{ $salesCount }})</td><td class="num">{{ $salesTotal }}</td></tr>
        <tr><td>{{ __('Taxes', [], $lang) }}</td><td class="num">{{ $taxTotal }}</td></tr>
        @foreach ($payments as $payment)
            <tr><td class="muted">{{ $payment['method'] }}</td><td class="num">{{ $payment['amount'] }}</td></tr>
        @endforeach
        @if ($pending)
            <tr><td class="muted">{{ __('Waiting to be confirmed', [], $lang) }}</td><td class="num">{{ $pending }}</td></tr>
        @endif
    </table>

    <div class="rule"></div>

    <table>
        <tr><td>{{ __('Opening cash', [], $lang) }}</td><td class="num">{{ $openingCash }}</td></tr>
        <tr><td>{{ __('Cash sales', [], $lang) }}</td><td class="num">{{ $cashSales }}</td></tr>
        <tr><td>{{ __('Cash refunds', [], $lang) }}</td><td class="num">{{ $cashRefunds }}</td></tr>
        <tr><td>{{ __('Cash in', [], $lang) }}</td><td class="num">{{ $cashIn }}</td></tr>
        <tr><td>{{ __('Cash out', [], $lang) }}</td><td class="num">{{ $cashOut }}</td></tr>
        <tr class="total"><td>{{ __('Cash expected', [], $lang) }}</td><td class="num">{{ $expected }}</td></tr>
        @if ($counted)
            <tr><td>{{ __('Cash counted', [], $lang) }}</td><td class="num">{{ $counted }}</td></tr>
            <tr class="total"><td>{{ __('Variance', [], $lang) }}</td><td class="num">{{ $variance }}</td></tr>
            @if ($varianceReason)
                <tr><td class="muted small" colspan="2">{{ $varianceReason }}</td></tr>
            @endif
        @endif
    </table>

    @if (! $counted)
        <div class="rule"></div>
        <div class="center small">{{ __('Not a closing: the session stays open.', [], $lang) }}</div>
    @endif

    @if ($poweredBy)
        <div class="center small muted" style="margin-top: 8px">{{ __('Powered by izSuite', [], $lang) }}</div>
    @endif
</main>
</body>
</html>
