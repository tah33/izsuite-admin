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
