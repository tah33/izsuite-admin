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
@case ('order_shipped')
{!! $order['rest'] ? 'Part of your order' : 'Your order' !!} {!! $order['number'] !!} is on its way{!! $order['rest'] ? ' - the rest follows' : '' !!}. Here is what was sent:
@break
@case ('order_ready')
Your order {!! $order['number'] !!} is ready to collect at {!! $order['place'] !!}. Here is what you ordered:
@break
@case ('order_delivered')
{!! $order['delivery_title'] === 'Collected' ? 'You collected your order' : 'Your order' !!} {!! $order['number'] !!}{!! $order['delivery_title'] === 'Collected' ? '' : ' was delivered' !!} on {!! $order['completed_on'] !!}. Thank you for shopping with us! Here is what {!! $order['delivery_title'] === 'Collected' ? 'you collected' : 'was delivered' !!}:
@break
@case ('digital_delivered')
Here is what we delivered from your order {!! $order['number'] !!}:
@break
@case ('service_performed')
This service from your order {!! $order['number'] !!} was performed on {!! $order['performed_on'] !!}:
@break
@default
Thank you for your order {!! $order['number'] !!}, placed on {!! $order['placed_on'] !!}. Here is what we have:
@endswitch

@foreach ($order['lines'] as $line)
- {!! $line['quantity'] !!} × {!! $line['title'] !!}{!! $line['options'] ? ' ('.$line['options'].')' : '' !!}{!! $line['amount'] !== '' ? ': '.$line['amount'] : '' !!}
@endforeach

@foreach ($order['totals'] as $label => $amount)
{!! $label !!}: {!! $amount !!}
@endforeach

{!! $order['delivery_title'] !!}
{!! $order['delivery'] !!}
@if ($order['payment_shown'] ?? true)

Payment: {!! $order['payment'] !!}
{!! $order['payment_note'] !!}
@endif
@if ($order['instructions'])

{!! $order['instructions'] !!}
@endif
@if ($order['link'])

{!! ['order_received' => 'See your order', 'payment_link' => 'Pay for your order', 'order_shipped' => 'Track your parcel'][$order['kind']] ?? 'See your invoice' !!}: {!! $order['link'] !!}
@endif

Questions? Reply to this e-mail{!! $supportPhone ? ' or call '.$supportPhone : '' !!}.

Sent with izSuite
