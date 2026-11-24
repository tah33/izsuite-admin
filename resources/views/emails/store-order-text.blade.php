{!! $storeName !!}

Dear {!! $order['customer'] !!},

@if ($order['kind'] === 'order_confirmed')
Your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}, is confirmed. Its invoice is {!! $order['invoice'] !!}. Here is what you ordered:
@else
Thank you for your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}. Here is what we have:
@endif

@foreach ($order['lines'] as $line)
- {!! $line['quantity'] !!} × {!! $line['title'] !!}{!! $line['options'] ? ' ('.$line['options'].')' : '' !!}: {!! $line['amount'] !!}
@endforeach

@foreach ($order['totals'] as $label => $amount)
{!! $label !!}: {!! $amount !!}
@endforeach

{!! $order['delivery_title'] !!}
{!! $order['delivery'] !!}

Payment: {!! $order['payment'] !!}
{!! $order['payment_note'] !!}
@if ($order['instructions'])

{!! $order['instructions'] !!}
@endif

{!! $order['kind'] === 'order_confirmed' ? 'See your invoice' : 'See your order' !!}: {!! $order['link'] !!}

Questions? Reply to this e-mail{!! $supportPhone ? ' or call '.$supportPhone : '' !!}.

Sent with izSuite
