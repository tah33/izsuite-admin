@php
    $subscription = $subscription ?? $invoice->subscription;
    $user = $user ?? $invoice->user;
    $useCustomFonts = $use_custom_fonts ?? true;

    $currency = strtoupper($invoice->currency ?? $subscription?->currency ?? 'USD');
    $amount = (float) ($invoice->amount ?? $subscription?->amount ?? 0);
    $amountText = $currency === 'USD' ? '$'.number_format($amount, 2) : number_format($amount, 2).' '.$currency;
    $taxText = $currency === 'USD' ? '$0.00' : '0.00 '.$currency;
    $invoiceNumber = $invoice->invoice_number ?: 'INV-'.$invoice->id;
    $issuedAt = ($invoice->paid_at ?? $invoice->created_at)?->format('M d, Y') ?? now()->format('M d, Y');
    $status = ucfirst((string) ($invoice->status ?: 'pending'));
    $statusClass = strtolower((string) $invoice->status) === 'paid' ? 'status-paid' : 'status-pending';
    $isPaid = strtolower((string) $invoice->status) === 'paid';
    $billingCycle = ucfirst((string) ($subscription?->billing_cycle ?: 'monthly'));
    $planName = $subscription?->name ?: 'Recruiter Plan';
    $paymentMethod = ucfirst((string) ($invoice->payment_method ?: $subscription?->payment_method_slug ?: 'Online'));
    $paymentReference = $invoice->payment_id ?: $invoice->transaction_id ?: $subscription?->payment_id;
    $periodStart = $subscription?->start_date?->format('M d, Y') ?? $invoice->created_at?->format('M d, Y');
    $periodEnd = $subscription?->next_renewal_date?->format('M d, Y');
    $periodText = $periodStart && $periodEnd ? "{$periodStart} - {$periodEnd}" : $billingCycle;
    $description = $invoice->description ?: "{$planName} subscription";
    $companyName = $user?->preferences['company_name'] ?? '';
    $successIcon = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22"><circle cx="11" cy="11" r="10" fill="#15803d"/><path d="M6.3 11.1l3 3 6.4-6.8" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/></svg>');
    $brandIcon = 'data:image/svg+xml;base64,'.base64_encode(
        file_get_contents(public_path(config('brand.assets.icon_blue')))
    );
    $brandName = setting('site_name', config('brand.name'));
    $manropeRegular = public_path('fonts/Manrope-Regular.ttf');
    $manropeSemiBold = public_path('fonts/Manrope-SemiBold.ttf');
    $manropeBold = public_path('fonts/Manrope-Bold.ttf');
    $manropeExtraBold = public_path('fonts/Manrope-ExtraBold.ttf');
    $fontCss = '';

    if ($useCustomFonts && is_file($manropeRegular) && is_file($manropeSemiBold) && is_file($manropeBold) && is_file($manropeExtraBold)) {
        $manropeRegularUrl = 'file://'.str_replace('\\', '/', $manropeRegular);
        $manropeSemiBoldUrl = 'file://'.str_replace('\\', '/', $manropeSemiBold);
        $manropeBoldUrl = 'file://'.str_replace('\\', '/', $manropeBold);
        $manropeExtraBoldUrl = 'file://'.str_replace('\\', '/', $manropeExtraBold);

        $fontCss = "
            @font-face { font-family: 'ManropePdf'; font-style: normal; font-weight: 400; src: url('{$manropeRegularUrl}') format('truetype'); }
            @font-face { font-family: 'ManropePdf'; font-style: normal; font-weight: 600; src: url('{$manropeSemiBoldUrl}') format('truetype'); }
            @font-face { font-family: 'ManropePdf'; font-style: normal; font-weight: 700; src: url('{$manropeBoldUrl}') format('truetype'); }
            @font-face { font-family: 'ManropePdf'; font-style: normal; font-weight: 800; src: url('{$manropeExtraBoldUrl}') format('truetype'); }
        ";
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoiceNumber }}</title>
    <style>
        {!! $fontCss !!}

        @page {
            margin: 20px;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: ManropePdf, DejaVu Sans, sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .page {
            padding: 0;
        }

        .invoice-card {
            overflow: visible;
            background: #ffffff;
            border-radius: 18px;
            padding: 24px 26px 10px;
        }

        .header-table,
        .info-table,
        .totals-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 30px;
            table-layout: fixed;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand-row {
            margin-bottom: 22px;
        }

        .brand-mark {
            display: inline-block;
            width: 34px;
            height: 34px;
            vertical-align: middle;
        }

        .brand-mark img {
            display: block;
            width: 34px;
            height: 34px;
        }

        .brand-name {
            display: inline-block;
            margin-left: 9px;
            color: #0f172a;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.6px;
            vertical-align: middle;
        }

        .company {
            color: #475569;
        }

        .company strong {
            display: block;
            color: #0f172a;
            font-weight: 800;
        }

        .receipt-title {
            color: {{ config('brand.colors.primary') }};
            font-size: 30px;
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1;
            margin: 0 0 14px;
            text-align: right;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .eyebrow {
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .invoice-number {
            color: #0f172a;
            font-size: 16px;
            font-weight: 800;
            text-align: right;
            letter-spacing: 0;
            word-spacing: normal;
            font-family: DejaVu Sans, sans-serif;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .info-table {
            margin-bottom: 30px;
            table-layout: fixed;
        }

        .info-cell {
            vertical-align: top;
        }

        .info-gap {
            width: 18px;
        }

        .panel {
            height: 178px;
            padding: 20px;
            background: #f1f3fd;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-title {
            margin: 0 0 14px;
            color: #64748b;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .bill-name {
            margin: 0 0 3px;
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
        }

        .muted {
            color: #64748b;
        }

        .detail-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-grid td {
            width: 50%;
            padding: 0 12px 13px 0;
            vertical-align: top;
        }

        .detail-label {
            color: #64748b;
            font-size: 9px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .detail-value {
            color: #0f172a;
            font-weight: 800;
            letter-spacing: 0;
            word-spacing: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            font-family: DejaVu Sans, sans-serif;
        }

        .value-empty {
            display: inline-block;
            min-height: 16px;
            min-width: 8px;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .items {
            width: 100%;
            margin-bottom: 24px;
            border-collapse: collapse;
        }

        .items th {
            padding: 13px 0;
            border-bottom: 2px solid #0f172a;
            color: #64748b;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.2px;
            text-align: left;
            text-transform: uppercase;
        }

        .items th.center,
        .items td.center {
            text-align: center;
        }

        .items th.right,
        .items td.right {
            text-align: right;
        }

        .items td {
            padding: 17px 0;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .item-title {
            margin: 0 0 2px;
            color: #0f172a;
            font-size: 17px;
            font-weight: 900;
        }

        .item-note {
            margin: 0;
            color: #64748b;
            font-size: 12px;
        }

        .totals-wrap {
            width: 100%;
            margin-bottom: 24px;
            text-align: right;
        }

        .totals-box {
            display: inline-block;
            width: 270px;
            text-align: left;
        }

        .totals-table td {
            padding: 6px 0;
            color: #64748b;
            font-weight: 700;
        }

        .totals-table td:last-child {
            text-align: right;
            color: #0f172a;
            font-weight: 900;
        }

        .total-row td {
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .total-row td:last-child {
            color: {{ config('brand.colors.primary') }};
            font-size: 27px;
        }

        .success-box {
            width: 100%;
            margin: 24px 0 24px;
            padding: 18px 24px;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 14px;
        }

        .success-icon {
            width: 38px;
            vertical-align: top;
            padding-top: 2px;
        }

        .success-icon img {
            display: block;
            width: 18px;
            height: 18px;
        }

        .success-title {
            margin: 0 0 6px;
            color: #166534;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .success-copy {
            margin: 0;
            color: #15803d;
            font-size: 13px;
            line-height: 1.55;
        }

        .footer-table {
            border-top: 1px solid #e2e8f0;
        }

        .footer-copy {
            padding-top: 16px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }

        .bar {
            display: inline-block;
            height: 5px;
            margin-left: 9px;
            border-radius: 999px;
            background: {{ config('brand.colors.primary') }};
            vertical-align: middle;
        }

        .bar.one {
            width: 46px;
        }

        .bar.two {
            width: 30px;
            opacity: 0.45;
        }

        .bar.three {
            width: 16px;
            opacity: 0.24;
        }
    </style>
</head>
<body>
<div class="invoice-card">
        <table class="header-table">
            <tr>
                <td style="width: 54%;">
                    <div class="brand-row">
                        <span class="brand-mark"><img src="{{ $brandIcon }}" alt=""></span>
                        <span class="brand-name">{{ $brandName }}</span>
                    </div>
                    <div class="company">
                        <strong>{{ $brandName }}</strong>
                        <div>{{ setting('site_description', config('brand.tagline')) }}</div>
                        @if($supportEmail = setting('support_email'))
                            <div>{{ $supportEmail }}</div>
                        @endif
                        @if($siteUrl = setting('site_url'))
                            <div>{{ preg_replace('#^https?://#', '', rtrim($siteUrl, '/')) }}</div>
                        @endif
                    </div>
                </td>
                <td style="width: 46%; text-align: right;">
                    <h1 class="receipt-title">RECEIPT</h1>
                    <div class="eyebrow">Invoice Number</div>
                    <div class="invoice-number">#{{ $invoiceNumber }}</div>
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td class="info-cell" style="width: 48%;">
                    <div class="panel">
                        <h2 class="panel-title">Billed To</h2>
                        <p class="bill-name">{{ $user?->name ?: '' }}</p>
                        <div>{{ $user?->email ?: '' }}</div>
                        <div class="muted">{{ $companyName }}</div>
                    </div>
                </td>
                <td class="info-gap"></td>
                <td class="info-cell" style="width: 48%;">
                    <div class="panel">
                        <h2 class="panel-title">Payment Details</h2>
                        <table class="detail-grid">
                            <tr>
                                <td>
                                    <div class="detail-label">Date Issued</div>
                                    <div class="detail-value">{{ $issuedAt ?: '' }}</div>
                                </td>
                                <td>
                                    <div class="detail-label">Billing Period</div>
                                    <div class="detail-value">{{ $billingCycle ?: '' }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="detail-label">Payment Method</div>
                                    <div class="detail-value">{{ $paymentMethod ?: '' }}</div>
                                </td>
                                <td>
                                    <div class="detail-label">Status</div>
                                    <span class="status-pill {{ $statusClass }}">{{ $status }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <div class="detail-label">Payment Reference</div>
                                    <div class="detail-value">
                                        @if($paymentReference)
                                            {{ $paymentReference }}
                                        @else
                                            <span class="value-empty"></span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
            <tr>
                <th>Description</th>
                <th class="center" style="width: 60px;">Qty</th>
                <th class="right" style="width: 110px;">Price</th>
                <th class="right" style="width: 120px;">Amount</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <p class="item-title">{{ $planName }}</p>
                    <p class="item-note">{{ $description }} @if($periodText)({{ $periodText }})@endif</p>
                </td>
                <td class="center"><strong>1</strong></td>
                <td class="right"><strong>{{ $amountText }}</strong></td>
                <td class="right"><strong>{{ $amountText }}</strong></td>
            </tr>
            </tbody>
        </table>

        <div class="totals-wrap">
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal</td>
                        <td>{{ $amountText }}</td>
                    </tr>
                    <tr>
                        <td>Tax (0%)</td>
                        <td>{{ $taxText }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Paid</td>
                        <td>{{ $amountText }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="success-box">
            <tr>
                <td class="success-icon"><img src="{{ $successIcon }}" alt=""></td>
                <td>
                    <p class="success-title">{{ $isPaid ? 'Payment Successful' : 'Payment '.$status }}</p>
                    <p class="success-copy">
                        Thank you for your business. Your recruiter features are now active. This invoice serves as an official receipt of payment for your records.
                    </p>
                </td>
            </tr>
        </table>

        <table class="footer-table">
            <tr>
                <td class="footer-copy">
                    Generated by {{ $brandName }} &mdash; {{ config('brand.tagline') }}.
                </td>
                <td class="footer-copy" style="text-align: right;">
                    <span class="bar one"></span>
                    <span class="bar two"></span>
                    <span class="bar three"></span>
                </td>
            </tr>
        </table>
</div>
</body>
</html>
