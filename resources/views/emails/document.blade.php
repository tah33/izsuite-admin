{{-- A business's document email (Doc 06 section 15): its own text, escaped,
     with its lines kept and web addresses made links; the PDF is attached. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $senderName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="padding:20px 32px; border-bottom:1px solid #eceef1; color:#1a1a2e; font-size:16px; font-weight:600;">{{ $senderName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px; color:#33354a; font-size:15px; line-height:1.6;">
                            {!! preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#1d4ed8;">$1</a>', nl2br(e($body), false)) !!}
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
