{!! $storeName !!}

{!! $cart['name'] ? "Hello {$cart['name']}," : 'Hello,' !!}

You left these in your cart at {!! $storeName !!}. They are kept for you:

@foreach ($cart['lines'] as $line)
{!! $line['quantity'] !!} × {!! $line['title'] !!}: {!! $line['amount'] !!}
@endforeach
Total: {!! $cart['total'] !!}

Prices, stock and delivery are checked again at checkout.

Return to your cart: {!! $cart['link'] !!}

You get this e-mail because you agreed to news and offers by e-mail from {!! $storeName !!}. To stop them, reply to this e-mail{!! $cart['account'] ? ', or change it in your account: '.$cart['account'] : '' !!}.
