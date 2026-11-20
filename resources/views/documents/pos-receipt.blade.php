{{-- A POS sale's receipt (Doc 18 section 18.19 - Phase C4), for an 80 mm
     roll: the business - its logo, address and tax numbers - and the
     location, the receipt number and when, the register and the cashier,
     the customer when one was chosen, each item with its quantity, price
     and discount, the totals and taxes, how it was paid - what was handed
     over, and the change - and the return policy. A copy after the first
     says Reprint (PosReceiptService). A return's refund receipt (Phase E2)
     is drawn here too, headed so, naming its sale. --}}
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

    @if ($reprint)
        <div class="reprint">{{ __('REPRINT', [], $lang) }}</div>
    @endif
    @if ($heading ?? null)
        <div class="center business" style="margin-top: 6px">{{ __($heading, [], $lang) }}</div>
    @endif

    <div class="rule"></div>

    <table>
        <tr><td>{{ __($numberLabel ?? 'Receipt', [], $lang) }}</td><td class="num"><strong>{{ $number }}</strong></td></tr>
        @if ($sale ?? null)
            <tr><td>{{ __('Sale', [], $lang) }}</td><td class="num">{{ $sale }}</td></tr>
        @endif
        <tr><td>{{ __('Date', [], $lang) }}</td><td class="num">{{ $when }}</td></tr>
        <tr><td>{{ __('Register', [], $lang) }}</td><td class="num">{{ $register }}</td></tr>
        @if ($cashier)
            <tr><td>{{ __('Cashier', [], $lang) }}</td><td class="num">{{ $cashier }}</td></tr>
        @endif
        @if ($customer)
            <tr><td>{{ __('Customer', [], $lang) }}</td><td class="num">{{ $customer }}</td></tr>
        @endif
    </table>

    <div class="rule"></div>

    <table>
        @foreach ($lines as $line)
            <tr><td class="item" colspan="2">{{ $line['name'] }}</td></tr>
            <tr>
                <td class="muted">{{ $line['quantity'] }} {{ $line['unit'] }} × {{ $line['price'] }}</td>
                <td class="num">{{ $line['amount'] }}</td>
            </tr>
            @if ($line['discount'])
                <tr><td class="muted small" colspan="2">{{ __('Discount', [], $lang) }} −{{ $line['discount'] }}</td></tr>
            @endif
            @if ($line['note'])
                <tr><td class="muted small" colspan="2">{{ $line['note'] }}</td></tr>
            @endif
        @endforeach
    </table>

    <div class="rule"></div>

    <table>
        <tr><td>{{ __('Subtotal', [], $lang) }}</td><td class="num">{{ $subtotal }}</td></tr>
        @if ($discount)
            <tr><td>{{ __('Discount', [], $lang) }}</td><td class="num">−{{ $discount }}</td></tr>
        @endif
        @foreach ($taxes as $tax)
            <tr><td>{{ $tax['name'] }} {{ $tax['rate'] }}{{ $inclusive ? ' ('.__('included', [], $lang).')' : '' }}</td><td class="num">{{ $tax['amount'] }}</td></tr>
        @endforeach
        @if ($rounding)
            <tr><td>{{ __('Cash rounding', [], $lang) }}</td><td class="num">{{ $rounding }}</td></tr>
        @endif
        <tr class="total"><td>{{ __('Total', [], $lang) }}</td><td class="num">{{ $total }}</td></tr>
    </table>

    <div class="rule"></div>

    <table>
        @if ($exchange ?? null)
            <tr><td>{{ __('Exchange', [], $lang) }} · {{ $exchange['number'] }}</td><td class="num">{{ $exchange['amount'] }}</td></tr>
        @endif
        @if ($credit)
            <tr><td>{{ __('Customer credit', [], $lang) }}</td><td class="num">{{ $credit }}</td></tr>
        @endif
        @foreach ($payments as $payment)
            <tr><td>{{ $payment['method'] }}{{ $payment['reference'] ? ' · '.$payment['reference'] : '' }}{{ $payment['status'] ? ' ('.__($payment['status'], [], $lang).')' : '' }}</td><td class="num">{{ $payment['amount'] }}</td></tr>
            @if ($payment['tendered'])
                <tr><td class="muted small" colspan="2">{{ __('Tendered', [], $lang) }} {{ $payment['tendered'] }}</td></tr>
            @endif
        @endforeach
        @if ($onAccount)
            <tr><td>{{ __('On account', [], $lang) }} · {{ __('due', [], $lang) }} {{ $due }}</td><td class="num">{{ $onAccount }}</td></tr>
        @endif
        @if ($credited ?? null)
            <tr><td>{{ __($creditedAs ?? 'Kept as credit', [], $lang) }}</td><td class="num">{{ $credited }}</td></tr>
        @endif
        @if ($paidBack ?? null)
            <tr class="total"><td>{{ __('Paid back', [], $lang) }}</td><td class="num">{{ $paidBack }}</td></tr>
        @endif
        @if ($change)
            <tr class="total"><td>{{ __('Change', [], $lang) }}</td><td class="num">{{ $change }}</td></tr>
        @endif
    </table>

    @if ($returnPolicy || $footer)
        <div class="rule"></div>
        @if ($returnPolicy)
            <div class="policy small">{{ $returnPolicy }}</div>
        @endif
        @if ($footer)
            <div class="center small" style="margin-top: 4px">{{ $footer }}</div>
        @endif
    @endif

    @if ($poweredBy)
        <div class="center small muted" style="margin-top: 8px">{{ __('Powered by izSuite', [], $lang) }}</div>
    @endif
</main>
</body>
</html>
