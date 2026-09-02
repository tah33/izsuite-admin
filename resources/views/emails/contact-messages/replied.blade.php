<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $contactMessage->reply_subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    @include('emails.partials.brand-header')

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:700;">{{ __('Hello') }} {{ $contactMessage->name }},</h2>
                            <p style="margin:0 0 24px; color:#4a4a68; font-size:16px; line-height:1.7;">
                                {{ __('Thank you for reaching out to us. We have reviewed your inquiry regarding') }} "<strong>{{ $contactMessage->subject }}</strong>" {{ __('and have provided a response below:') }}
                            </p>

                            <!-- Response Box -->
                            <div style="background-color:#f9fafb; border-left:4px solid {{ config('brand.colors.primary') }}; padding:24px; margin:32px 0; border-radius:0 8px 8px 0;">
                                <div style="color:#1f2937; font-size:15px; line-height:1.8;">
                                    {!! $contactMessage->admin_reply !!}
                                </div>
                            </div>

                            <p style="margin:32px 0 0; color:#6b7280; font-size:14px; line-height:1.6;">
                                {{ __('If you have any further questions or need additional assistance, feel free to reply to this email or visit our website.') }}
                            </p>
                            
                            <div style="margin-top:32px; padding-top:24px; border-t:1px solid #e5e7eb;">
                                <p style="margin:0; color:#1a1a2e; font-size:15px; font-weight:600;">{{ __('Best regards,') }}</p>
                                <p style="margin:4px 0 0; color:{{ config('brand.colors.primary') }}; font-size:15px; font-weight:700;">{{ setting('site_name', config('brand.name')) }} {{ __('Team') }}</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:32px 40px; background-color:#f8fafc; text-align:center;">
                            <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.5;">
                                &copy; {{ date('Y') }} {{ setting('site_name', config('brand.name')) }} &mdash; {{ config('brand.tagline') }}<br>
                                {{ __('This is an automated notification from our support desk.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
