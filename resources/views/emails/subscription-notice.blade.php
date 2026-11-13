{{-- The daily subscription cycle's notices (SubscriptionNoticeMail): a trial ending, a day of grace, a plan ended - and the saved card not charged (Phase D3). --}}
@php
    $plan = $details['plan'];
    $days = $details['days_left'] ?? null;
    $left = $days === 0 ? 'today is the last day' : ($days === 1 ? 'you have 1 day left' : "you have {$days} days left");
    $why  = [
        'trial'     => "Your {$plan} trial ended without its invoice being paid,",
        'grace'     => "Your {$plan} subscription was not renewed in time,",
        'cancelled' => "As you asked, your {$plan} subscription has ended at the close of its paid period,",
        'ended'     => "Your {$plan} subscription ended without being renewed,",
    ][$details['reason'] ?? 'ended'] ?? "Your {$plan} subscription has ended,";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your subscription</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    @include('emails.partials.brand-header')

                    <tr>
                        <td style="padding:40px;">
                            @if ($kind === 'trial_ending')
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Your {{ $plan }} trial ends {{ $days === 1 ? 'tomorrow' : 'in '.$days.' days' }}</h2>
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                    The trial for {{ $details['business'] }} runs until {{ $details['trial_ends_on'] }}. To keep {{ $plan }}, pay invoice
                                    {{ $details['invoice'] }} ({{ $details['total'] }}) from your Subscription page by then. Otherwise the business goes back to
                                    Billing Starter, the free plan, the day after - everything you recorded is kept.
                                </p>
                            @elseif ($kind === 'refunded')
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">izSuite has refunded {{ $details['amount'] }}</h2>
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                    We have given back {{ $details['amount'] }} for {{ $details['business'] }}{{ $details['invoice'] ? ', paid for invoice '.$details['invoice'] : '' }}.
                                    @if ($details['method'] === 'Bank transfer')
                                        It was sent to your bank account by transfer, and can take a few days to arrive.
                                    @else
                                        It goes back to {{ $details['method'] }}; your bank can take 5 to 10 days to show it.
                                    @endif
                                    Your plan is not changed by it.
                                </p>
                            @elseif ($kind === 'payment_failed')
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">{{ $details['confirm'] ? 'Please confirm your payment' : 'We could not charge your card' }}</h2>
                                <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">
                                    @if ($details['confirm'])
                                        Your bank asks you to confirm the payment of invoice {{ $details['invoice'] }} ({{ $details['total'] }}) with {{ $details['card'] }}.
                                        Pay it with Pay by Card on your Subscription page, where your bank can check it is you.
                                    @else
                                        We tried to charge {{ $details['card'] }} for invoice {{ $details['invoice'] }} ({{ $details['total'] }}), but it did not go through: {{ $details['declined'] }}
                                    @endif
                                </p>
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                    @if ($details['for'] === 'trial')
                                        The {{ $plan }} trial for {{ $details['business'] }} ends today. Pay the invoice on your Subscription page today to keep
                                        {{ $plan }}; otherwise the business goes back to Billing Starter, the free plan, tomorrow. Everything you recorded is kept.
                                    @elseif ($details['grace_days'] > 0)
                                        The paid period for {{ $details['business'] }} ends today. {{ $details['grace_days'] }} days of grace follow, and we will try
                                        your card again - or pay on your Subscription page, by card or bank transfer.
                                    @else
                                        The paid period for {{ $details['business'] }} ends today. Pay the invoice on your Subscription page today to keep {{ $plan }}.
                                    @endif
                                </p>
                            @elseif ($kind === 'grace')
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Your {{ $plan }} subscription has ended</h2>
                                <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">
                                    The paid period for {{ $details['business'] }} is over, and <strong>{{ $left }}</strong> to renew it.
                                    Pay invoice {{ $details['invoice'] }} ({{ $details['total'] }}) from your Subscription page by {{ $details['last_day'] }}.
                                </p>
                                @if (! empty($details['card']))
                                    <p style="margin:0 0 16px; color:#51545e; font-size:15px; line-height:1.6;">
                                        {{ $details['confirm'] ? "Your bank asks you to confirm the payment with {$details['card']}: pay with Pay by Card on your Subscription page." : "We tried {$details['card']} again today, but it did not go through: {$details['declined']}" }}
                                    </p>
                                @endif
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                    Otherwise your plan will be cancelled on {{ $details['cancels_on'] }}, and the business goes back to Billing Starter,
                                    the free plan. Everything you recorded is kept.
                                </p>
                            @else
                                <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Your {{ $plan }} plan has ended</h2>
                                <p style="margin:0 0 28px; color:#51545e; font-size:15px; line-height:1.6;">
                                    {{ $why }} so {{ $details['business'] }} is now on Billing Starter, the free plan. Everything you recorded is kept;
                                    apps the free plan does not include are turned off, and their Lite editions come back. You can choose a paid plan
                                    again whenever you like.
                                </p>
                            @endif

                            <p style="margin:0 0 28px; text-align:center;">
                                <a href="{{ $link }}" style="display:inline-block; padding:12px 28px; border-radius:6px; background-color:#2563eb; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none;">See your subscription</a>
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
