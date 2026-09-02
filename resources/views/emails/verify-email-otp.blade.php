<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email Address</title>
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
                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Welcome to {{ setting('site_name', config('brand.name')) }}!</h2>
                            <p style="margin:0 0 24px; color:#51545e; font-size:15px; line-height:1.6;">
                                Please verify your email address to activate your account. Use the OTP below to proceed. This code is valid for <strong>30 minutes</strong>.
                            </p>

                            <!-- OTP Box -->
                            <div style="text-align:center; margin:32px 0;">
                                <div style="display:inline-block; background-color:#f4f4f7; border:2px dashed {{ config('brand.colors.primary') }}; border-radius:8px; padding:20px 40px;">
                                    <span style="font-size:36px; font-weight:700; letter-spacing:8px; color:{{ config('brand.colors.primary') }};">{{ $otp }}</span>
                                </div>
                            </div>

                            <p style="margin:0 0 8px; color:#51545e; font-size:14px; line-height:1.6;">
                                If you did not create an account, please ignore this email.
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
