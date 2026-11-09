{{-- izSuite's invoice to a business for its subscription - or, paid, its
     receipt (Doc 13 section 15) - drawn for its PDF by SubscriptionPdf from
     what the invoice kept when issued. Not one of the business's documents. --}}
@php
    $money  = fn ($amount) => $invoice->currency.' '.number_format((float) $amount, 2);
    $day    = fn ($date) => $date?->format('j M Y');
    $seller = $invoice->seller;
    $billed = $invoice->billed_to;
    $title  = $receipt ? 'Receipt' : 'Invoice';
    $period = $invoice->period_starts_on
        ? $day($invoice->period_starts_on).' to '.$day($invoice->period_ends_on)
        : ($invoice->cycle === 'yearly' ? 'One year from payment' : 'One month from payment');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $receipt ? $invoice->receipt_number : $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Noto Sans', 'Liberation Sans', Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #1f2937; }
        .head { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 18px; border-bottom: 2px solid #1769e8; }
        .seller strong { display: block; font-size: 16px; color: #111827; }
        .seller__logo { display: block; width: auto; height: 34px; max-width: 220px; margin-bottom: 8px; }
        .muted { color: #6b7280; }
        .lines-pre { white-space: pre-line; }
        h1 { margin: 0 0 8px; font-size: 24px; letter-spacing: 0.04em; text-transform: uppercase; color: #1769e8; text-align: right; }
        .meta { border-collapse: collapse; margin-left: auto; }
        .meta td { padding: 1px 0 1px 16px; }
        .meta td:first-child { color: #6b7280; }
        .meta td:last-child { text-align: right; font-weight: 600; }
        .parties { margin: 22px 0; }
        .label { margin: 0 0 4px; font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7280; }
        table.lines { width: 100%; border-collapse: collapse; }
        .lines th { padding: 8px 10px; background: #f3f4f6; font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; text-align: left; color: #4b5563; }
        .lines td { padding: 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .lines .amount { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin: 12px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 4px 10px; }
        .totals td:last-child { text-align: right; white-space: nowrap; }
        .totals .grand td { padding-top: 8px; border-top: 2px solid #111827; font-size: 14px; font-weight: 700; }
        .tax-note { width: 280px; margin: 6px 0 0 auto; padding: 0 10px; font-size: 11px; color: #4b5563; }
        .note { margin-top: 26px; padding: 12px 14px; border-radius: 6px; background: #f3f4f6; }
        .note h2 { margin: 0 0 6px; font-size: 12px; }
        .note.paid { background: #ecfdf5; color: #065f46; }
        .note.void { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
    <header class="head">
        <div class="seller">
            {{-- The logo in place of the name; the name heads the details under it, as the legal seller. --}}
            @if ($logo ?? null)
                <img class="seller__logo" src="{{ $logo }}" alt="{{ $seller['name'] ?? 'izSuite' }}">
                @if (! empty($seller['address']) || ! empty($seller['email']) || ! empty($seller['tax_id']))<div>{{ $seller['name'] ?? 'izSuite' }}</div>@endif
            @else
                <strong>{{ $seller['name'] ?? 'izSuite' }}</strong>
            @endif
            @if (! empty($seller['address']))<div class="lines-pre muted">{{ $seller['address'] }}</div>@endif
            @if (! empty($seller['email']))<div class="muted">{{ $seller['email'] }}</div>@endif
            @if (! empty($seller['tax_id']))<div class="muted">Tax ID: {{ $seller['tax_id'] }}</div>@endif
        </div>
        <div>
            <h1>{{ $title }}</h1>
            <table class="meta">
                @if ($receipt)
                    <tr><td>Receipt</td><td>{{ $invoice->receipt_number }}</td></tr>
                    <tr><td>For invoice</td><td>{{ $invoice->number }}</td></tr>
                    <tr><td>Paid on</td><td>{{ $day($invoice->paid_at) }}</td></tr>
                @else
                    <tr><td>Invoice</td><td>{{ $invoice->number }}</td></tr>
                    <tr><td>Issued</td><td>{{ $day($invoice->issued_on) }}</td></tr>
                    <tr><td>Due by</td><td>{{ $day($invoice->due_on) }}</td></tr>
                @endif
            </table>
        </div>
    </header>

    <section class="parties">
        <p class="label">{{ $receipt ? 'Received from' : 'Billed to' }}</p>
        <strong>{{ $billed['name'] ?? '' }}</strong>
        @if (($billed['business'] ?? null) && ($billed['business'] !== ($billed['name'] ?? null)))<div>{{ $billed['business'] }}</div>@endif
        @if (! empty($billed['address']))<div class="lines-pre">{{ $billed['address'] }}</div>@endif
        @if (! empty($billed['country']))<div>{{ $billed['country'] }}</div>@endif
        @if (! empty($billed['tax_id']))<div>Tax ID: {{ $billed['tax_id'] }}</div>@endif
        @if (! empty($billed['email']))<div class="muted">{{ $billed['email'] }}</div>@endif
    </section>

    <table class="lines">
        <thead><tr><th>Description</th><th>Period</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            <tr>
                <td>izSuite {{ $invoice->package_name }} plan, {{ $invoice->cycle === 'yearly' ? 'yearly' : 'monthly' }} subscription</td>
                <td>{{ $period }}</td>
                <td class="amount">{{ $money($invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- The tax as worked out when issued (Phase D1); a reverse charge adds none and prints its note. --}}
    <table class="totals">
        @if ((float) $invoice->credit > 0 || $invoice->tax_name)
            <tr><td>Subtotal</td><td>{{ $money($invoice->amount) }}</td></tr>
        @endif
        @if ((float) $invoice->credit > 0)
            <tr><td>Credit for the unused plan</td><td>-{{ $money($invoice->credit) }}</td></tr>
        @endif
        @if ($invoice->tax_name)
            <tr><td>{{ $invoice->tax_name }} ({{ $invoice->taxPercent() }}%)</td><td>{{ $money($invoice->tax) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ $receipt ? 'Amount paid' : 'Total due' }}</td><td>{{ $money($invoice->total) }}</td></tr>
    </table>
    @if ($invoice->tax_note)
        <p class="tax-note">{{ $invoice->tax_note }}</p>
    @endif

    @if ($receipt)
        <div class="note paid">
            @if ($invoice->paid_with)
                Paid in full on {{ $day($invoice->paid_at) }} by card, {{ $invoice->paid_with }}{{ $invoice->payment_reference ? ', reference '.$invoice->payment_reference : '' }}. Thank you.
            @else
                Paid in full on {{ $day($invoice->paid_at) }} by bank transfer{{ $invoice->transfer_reference ? ', reference '.$invoice->transfer_reference : '' }}. Thank you.
            @endif
        </div>
    @elseif ($invoice->status === 'void')
        <div class="note void">This invoice was cancelled. Nothing is due on it.</div>
    @elseif ($invoice->status === 'paid')
        <div class="note paid">Paid on {{ $day($invoice->paid_at) }}. Receipt {{ $invoice->receipt_number }}.</div>
    @else
        <div class="note">
            <h2>How to pay</h2>
            @if ($invoice->payment_instructions)<div class="lines-pre">{{ $invoice->payment_instructions }}</div>@endif
            <p style="margin: 8px 0 0;">Please quote <strong>{{ $invoice->number }}</strong> as the payment reference.</p>
        </div>
    @endif
</body>
</html>
