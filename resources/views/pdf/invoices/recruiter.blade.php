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

@include('pdf.invoices.recruiter._styles-header')

@include('pdf.invoices.recruiter._styles-details')

@include('pdf.invoices.recruiter._styles-totals')
    </style>
</head>
<body>
@include('pdf.invoices.recruiter._invoice')
</body>
</html>
