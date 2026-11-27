{{-- An online order's e-mail to its customer (Doc 19 section 19.35): the
     store's name, the order's number, what was ordered and its totals, how it
     reaches them and how to pay it - received (Phase D5), with the order's
     own page; confirmed (Phase F2), with its invoice's; its payment received
     or not (Phase F3), with the invoice's once it has one; cancelled (Phase
     F4); a payment link (Phase F6a), with the link's own page; a shipment on
     its way with its tracking link, ready to collect, delivered or collected
     (Phase G1) - their payment only while it is owed before it arrives. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $storeName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="padding:20px 32px; border-bottom:1px solid #eceef1; color:#1a1a2e; font-size:16px; font-weight:600;">{{ $storeName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px; color:#33354a; font-size:15px; line-height:1.6;">
                            <p style="margin:0 0 12px;">Dear {{ $order['customer'] }},</p>
                            @switch ($order['kind'])
                                @case ('order_confirmed')
                                    <p style="margin:0 0 12px;">Your order <strong>{{ $order['number'] }}</strong>, placed on {{ $order['placed_on'] }}, is confirmed. Its invoice is <strong>{{ $order['invoice'] }}</strong>. Here is what you ordered:</p>
                                    @break
                                @case ('payment_received')
                                    <p style="margin:0 0 12px;">We have received your payment of <strong>{{ $order['paid'] }}</strong> for your order <strong>{{ $order['number'] }}</strong>{{ $order['reference'] ? ', reference '.$order['reference'] : '' }}. Thank you! Here is your order:</p>
                                    @break
                                @case ('payment_failed')
                                    <p style="margin:0 0 12px;">We could not confirm your payment for your order <strong>{{ $order['number'] }}</strong>, placed on {{ $order['placed_on'] }}: it is still waiting to be paid. Here is your order:</p>
                                    @break
                                @case ('order_cancelled')
                                    <p style="margin:0 0 12px;">Your order <strong>{{ $order['number'] }}</strong>, placed on {{ $order['placed_on'] }}, is cancelled. Here is what it was:</p>
                                    @break
                                @case ('payment_link')
                                    <p style="margin:0 0 12px;">You can pay <strong>{{ $order['asked'] }}</strong> for your order <strong>{{ $order['number'] }}</strong>, placed on {{ $order['placed_on'] }}, through the link below until {{ $order['until'] }}. Here is your order:</p>
                                    @break
                                @case ('order_shipped')
                                    <p style="margin:0 0 12px;">{{ $order['rest'] ? 'Part of your order' : 'Your order' }} <strong>{{ $order['number'] }}</strong> is on its way{{ $order['rest'] ? ' - the rest follows' : '' }}. Here is what was sent:</p>
                                    @break
                                @case ('order_ready')
                                    <p style="margin:0 0 12px;">Your order <strong>{{ $order['number'] }}</strong> is ready to collect at <strong>{{ $order['place'] }}</strong>. Here is what you ordered:</p>
                                    @break
                                @case ('order_delivered')
                                    <p style="margin:0 0 12px;">{{ $order['delivery_title'] === 'Collected' ? 'You collected your order' : 'Your order' }} <strong>{{ $order['number'] }}</strong>{{ $order['delivery_title'] === 'Collected' ? '' : ' was delivered' }} on {{ $order['completed_on'] }}. Thank you for shopping with us! Here is what {{ $order['delivery_title'] === 'Collected' ? 'you collected' : 'was delivered' }}:</p>
                                    @break
                                @default
                                    <p style="margin:0 0 12px;">Thank you for your order <strong>{{ $order['number'] }}</strong>, placed on {{ $order['placed_on'] }}. Here is what we have:</p>
                            @endswitch
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; color:#33354a;">
                                @foreach ($order['lines'] as $line)
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #eceef1;">{{ $line['quantity'] }} × {{ $line['title'] }}@if ($line['options']) <span style="color:#6b6f80;">({{ $line['options'] }})</span>@endif</td>
                                        <td style="padding:6px 0; border-bottom:1px solid #eceef1; text-align:right; white-space:nowrap;">{{ $line['amount'] }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($order['totals'] as $label => $amount)
                                    <tr>
                                        <td style="padding:6px 0;{{ $loop->last ? ' font-weight:700;' : '' }}">{{ $label }}</td>
                                        <td style="padding:6px 0; text-align:right; white-space:nowrap;{{ $loop->last ? ' font-weight:700;' : '' }}">{{ $amount }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0; color:#33354a; font-size:15px; line-height:1.6;">
                            <p style="margin:0 0 8px;"><strong>{{ $order['delivery_title'] }}</strong><br>{!! nl2br(e($order['delivery']), false) !!}</p>
                            @if ($order['payment_shown'] ?? true)
                                <p style="margin:0 0 8px;"><strong>Payment: {{ $order['payment'] }}</strong><br>{!! nl2br(e($order['payment_note']), false) !!}</p>
                            @endif
                            @if ($order['instructions'])
                                <p style="margin:0 0 8px; padding:12px; background-color:#f4f4f7; border-radius:6px;">{!! nl2br(e($order['instructions']), false) !!}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 28px;">
                            @if ($order['link'])
                                <a href="{{ $order['link'] }}" style="display:inline-block; padding:10px 18px; background-color:#1d4ed8; color:#ffffff; border-radius:6px; text-decoration:none; font-size:14px; font-weight:600;">{{ ['order_received' => 'See your order', 'payment_link' => 'Pay for your order', 'order_shipped' => 'Track your parcel'][$order['kind']] ?? 'See your invoice' }}</a>
                            @endif
                            <p style="margin:12px 0 0; color:#6b6f80; font-size:13px;">Questions? Reply to this e-mail{{ $supportPhone ? ' or call '.$supportPhone : '' }}.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background-color:#f4f4f7; color:#9a9ea6; font-size:12px; text-align:center;">
                            Sent with izSuite
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
