{{-- izSuite's invoice for a business's subscription, or its receipt (SubscriptionBillingMail); the PDF is attached. --}}
@php
    $money  = fn ($amount) => $invoice->currency.' '.number_format((float) $amount, 2);
    $cycle  = $invoice->cycle === 'yearly' ? 'yearly' : 'monthly';
    $name   = $invoice->billed_to['name'] ?? $invoice->billed_to['business'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $receipt ? 'Payment received' : 'Your invoice' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    @include('emails.partials.brand-header')

                    <tr>
                        <td style="padding:40px;">
                            @if ($receipt)
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Payment received - thank you</h2>
                                <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">
                                    Hi {{ $name }}, we have received {{ $money($invoice->total) }} for invoice {{ $invoice->number }}.
                                    Your {{ $invoice->package_name }} plan ({{ $cycle }}) is active from {{ $invoice->period_starts_on?->format('j M Y') }} to {{ $invoice->period_ends_on?->format('j M Y') }}.
                                </p>
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">Your receipt, {{ $invoice->receipt_number }}, is attached.</p>
                            @else
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Invoice {{ $invoice->number }}</h2>
                                <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">
                                    Hi {{ $name }}, here is your invoice for the {{ $invoice->package_name }} plan ({{ $cycle }}):
                                    <strong>{{ $money($invoice->total) }}</strong>, due by <strong>{{ $invoice->due_on->format('j M Y') }}</strong>.
                                </p>
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                    Pay it by bank transfer as the attached invoice shows, quoting {{ $invoice->number }} as the reference.
                                    Your plan starts - or carries on - as soon as we confirm the payment.
                                </p>
                            @endif

                            <p style="margin:0 0 28px; text-align:center;">
                                <a href="{{ $link }}" style="display:inline-block; padding:12px 28px; border-radius:6px; background-color:#2563eb; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none;">See your subscription</a>
                            </p>

                            <p style="margin:0; color:#9a9ea6; font-size:13px; line-height:1.6;">
                                Questions about your bill? Reply to this e-mail{{ ($invoice->seller['email'] ?? null) ? ' or write to '.$invoice->seller['email'] : '' }}.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 40px; background-color:#f4f4f7; text-align:center;">
                            <p style="margin:0; color:#9a9ea6; font-size:13px;">
                                &copy; {{ date('Y') }} {{ $invoice->seller['name'] ?? setting('site_name', config('brand.name')) }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
