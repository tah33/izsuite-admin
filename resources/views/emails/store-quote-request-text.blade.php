{!! $storeName !!}

Hello {!! $quote['name'] !!},

@if (($quote['kind'] ?? 'quote') === 'message')
We have your message, {!! $quote['number'] !!}. We will reply to this e-mail.

Your message:
{!! $quote['message'] !!}
@else
We have your request for a quotation, {!! $quote['number'] !!}. We will reply to this e-mail with our prices. You asked about:

@foreach ($quote['items'] as $item)
{!! $item['quantity'] !!} × {!! $item['title'] !!}
@endforeach

Delivered to: {!! $quote['delivery'] !!}
@if ($quote['required_on'])
Wanted by: {!! $quote['required_on'] !!}
@endif
@if ($quote['message'])

Your message:
{!! $quote['message'] !!}
@endif

A request for a quotation is not an order: nothing is charged until you accept our quotation.
@endif
