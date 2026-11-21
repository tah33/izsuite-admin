{{-- A code for a storefront customer (Doc 19 Phase E1): to verify their
     e-mail, or to reset their password, with how long it holds. --}}
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
                            <p style="margin:0 0 12px;">{{ $reset ? 'Use this code to choose a new password for your account:' : 'Use this code to verify your e-mail for your account:' }}</p>
                            <p style="margin:0 0 16px; font-size:28px; font-weight:700; letter-spacing:6px; color:#1a1a2e;">{{ $code }}</p>
                            <p style="margin:0; color:#6b6f80; font-size:13px;">It holds for {{ $minutes }} minutes. If you did not ask for it, you can leave this e-mail be.</p>
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
