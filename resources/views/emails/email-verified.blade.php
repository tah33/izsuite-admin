<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Activated</title>
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
                            <div style="text-align:center; margin:0 0 24px;">
                                <div style="display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:50%; background-color:#e6f7ed;">
                                    <span style="color:#1fa971; font-size:28px; line-height:1;">&#10003;</span>
                                </div>
                            </div>

                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600; text-align:center;">Your account is active</h2>

                            <p style="margin:0 0 24px; color:#51545e; font-size:15px; line-height:1.6;">
                                Hi {{ $firstName }}, <strong>{{ $email }}</strong> is verified and your izSuite account is now active. You can sign in whenever you're ready.
                            </p>

                            <div style="text-align:center; margin:32px 0 8px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block; background-color:{{ config('brand.colors.primary') }}; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:600; font-size:15px;">
                                    Sign in to your workspace
                                </a>
                            </div>
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
