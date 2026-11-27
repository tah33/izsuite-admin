{!! $storeName !!}

Dear {!! $order['customer'] !!},

@switch ($order['kind'])
@case ('order_confirmed')
Your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}, is confirmed. Its invoice is {!! $order['invoice'] !!}. Here is what you ordered:
@break
@case ('payment_received')
We have received your payment of {!! $order['paid'] !!} for your order {!! $order['number'] !!}{!! $order['reference'] ? ', reference '.$order['reference'] : '' !!}. Thank you! Here is your order:
@break
@case ('payment_failed')
We could not confirm your payment for your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}: it is still waiting to be paid. Here is your order:
@break
@case ('order_cancelled')
Your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}, is cancelled. Here is what it was:
@break
@case ('payment_link')
You can pay {!! $order['asked'] !!} for your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}, through the link below until {!! $order['until'] !!}. Here is your order:
@break
@default
Thank you for your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}. Here is what we have:
@endswitch

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
@if ($order['link'])

{!! ['order_received' => 'See your order', 'payment_link' => 'Pay for your order'][$order['kind']] ?? 'See your invoice' !!}: {!! $order['link'] !!}
@endif

Questions? Reply to this e-mail{!! $supportPhone ? ' or call '.$supportPhone : '' !!}.

Sent with izSuite
