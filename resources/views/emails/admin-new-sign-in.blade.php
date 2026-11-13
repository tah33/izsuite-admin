<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New sign-in to the admin panel</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    @include('emails.partials.brand-header')

                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">New sign-in to the admin panel</h2>

                            <p style="margin:0 0 20px; color:#51545e; font-size:15px; line-height:1.6;">
                                Hi {{ $firstName }}, your account signed in to the {{ setting('site_name', config('brand.name')) }} admin panel from a browser it has not used before.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px; font-size:14px; color:#51545e;">
                                <tr><td style="padding:4px 16px 4px 0; color:#9a9ea6;">When</td><td style="padding:4px 0;">{{ $signedInAt->format('F j, Y \a\t g:i A T') }}</td></tr>
                                <tr><td style="padding:4px 16px 4px 0; color:#9a9ea6;">Browser</td><td style="padding:4px 0;">{{ $browser }}</td></tr>
                                <tr><td style="padding:4px 16px 4px 0; color:#9a9ea6;">IP address</td><td style="padding:4px 0;">{{ $ipAddress ?? 'Unknown' }}</td></tr>
                            </table>

                            <p style="margin:0; color:#51545e; font-size:14px; line-height:1.6;">
                                If this was you, there is nothing else to do. If it was not, change your password now and tell a Super Admin, who can end the account's sessions and reset its two-factor sign-in.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 40px; background-color:#f4f4f7; text-align:center;">
                            <p style="margin:0; color:#9a9ea6; font-size:13px;">
                                &copy; {{ date('Y') }} {{ setting('site_name', config('brand.name')) }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
