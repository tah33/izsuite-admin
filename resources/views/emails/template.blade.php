{{-- A platform e-mail in the words the admin wrote (Doc 15 section 15.20 - Phase F2): its subject as the title, its paragraphs, and its button for one with a link. Expects $words. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $words['subject'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    @include('emails.partials.brand-header')

                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">{{ $words['subject'] }}</h2>

                            @foreach($words['paragraphs'] as $paragraph)
                                <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">{!! nl2br(e($paragraph)) !!}</p>
                            @endforeach

                            @if($words['link'] && $words['button'])
                                <p style="margin:12px 0 28px; text-align:center;">
                                    <a href="{{ $words['link'] }}" style="display:inline-block; padding:12px 28px; border-radius:6px; background-color:#2563eb; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none;">{{ $words['button'] }}</a>
                                </p>
                            @endif
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
