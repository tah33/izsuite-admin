{{-- An abandoned cart's recovery e-mail (Doc 19 Phase H3b): its products as
     last priced, the link back to the cart, and why it came - with how to
     stop such e-mails. --}}
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
                        <td style="padding:28px 32px; color:#33354a; font-size:15px; line-height:1.6;">
                            <p style="margin:0 0 12px;">{{ $cart['name'] ? "Hello {$cart['name']}," : 'Hello,' }}</p>
                            <p style="margin:0 0 16px;">You left these in your cart at {{ $storeName }}. They are kept for you:</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px; font-size:14px;">
                                @foreach ($cart['lines'] as $line)
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #eceef1;">{{ $line['quantity'] }} × {{ $line['title'] }}</td>
                                        <td style="padding:6px 0; border-bottom:1px solid #eceef1; text-align:right; white-space:nowrap;">{{ $line['amount'] }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding:8px 0 0; font-weight:600;">Total</td>
                                    <td style="padding:8px 0 0; font-weight:600; text-align:right; white-space:nowrap;">{{ $cart['total'] }}</td>
                                </tr>
                            </table>
                            <p style="margin:0 0 20px; color:#6b6f80; font-size:13px;">Prices, stock and delivery are checked again at checkout.</p>
                            <p style="margin:0 0 20px;">
                                <a href="{{ $cart['link'] }}" style="display:inline-block; padding:10px 20px; border-radius:6px; background-color:#2563eb; color:#ffffff; text-decoration:none; font-weight:600;">Return to Your Cart</a>
                            </p>
                            <p style="margin:0; color:#6b6f80; font-size:13px;">
                                You get this e-mail because you agreed to news and offers by e-mail from {{ $storeName }}. To stop them, reply to this e-mail{!! $cart['account'] ? ', or change it <a href="'.e($cart['account']).'" style="color:#2563eb;">in your account</a>' : '' !!}.
                            </p>
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
