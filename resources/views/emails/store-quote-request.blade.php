{{-- A request for a quotation's e-mail, saying it arrived (Doc 19 Phase
     H4a): its number, the products asked about, the delivery, the date
     wanted and the message - or (Phase H4e) a message's from the
     contact page: its number and the message. --}}
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
                            <p style="margin:0 0 12px;">Hello {{ $quote['name'] }},</p>
                            @if (($quote['kind'] ?? 'quote') === 'message')
                            <p style="margin:0 0 16px;">We have your message, <strong>{{ $quote['number'] }}</strong>. We will reply to this e-mail.</p>
                            <p style="margin:0; white-space:pre-line;"><strong>Your message:</strong><br>{{ $quote['message'] }}</p>
                            @else
                            <p style="margin:0 0 16px;">We have your request for a quotation, <strong>{{ $quote['number'] }}</strong>. We will reply to this e-mail with our prices. You asked about:</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px; font-size:14px;">
                                @foreach ($quote['items'] as $item)
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #eceef1;">{{ $item['quantity'] }} × {{ $item['title'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p style="margin:0 0 6px;"><strong>Delivered to:</strong> {{ $quote['delivery'] }}</p>
                            @if ($quote['required_on'])
                                <p style="margin:0 0 6px;"><strong>Wanted by:</strong> {{ $quote['required_on'] }}</p>
                            @endif
                            @if ($quote['message'])
                                <p style="margin:12px 0 0; white-space:pre-line;"><strong>Your message:</strong><br>{{ $quote['message'] }}</p>
                            @endif
                            <p style="margin:20px 0 0; color:#6b6f80; font-size:13px;">A request for a quotation is not an order: nothing is charged until you accept our quotation.</p>
                            @endif
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
