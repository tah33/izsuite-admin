<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You are invited</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    @include('emails.partials.brand-header')

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Join {{ $businessName }}</h2>

                            <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">
                                Hi {{ $inviteeName }}, {{ $inviterName }} invited you to work in <strong>{{ $businessName }}</strong> as <strong>{{ $roleName }}</strong>.
                            </p>

                            <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                Accept with the account you already have, or set one up - it takes a minute.
                            </p>

                            <p style="margin:0 0 28px; text-align:center;">
                                <a href="{{ $link }}" style="display:inline-block; padding:12px 28px; border-radius:6px; background-color:#2563eb; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none;">Accept the invitation</a>
                            </p>

                            <p style="margin:0; color:#9a9ea6; font-size:13px; line-height:1.6;">
                                The link works for {{ $days === 1 ? 'one day' : $days.' days' }}, once. If you were not expecting it, you can ignore this e-mail - nothing happens until the link is used.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
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
