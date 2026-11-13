{{-- Card tries that did not go through (Doc 15 section 14): by month and currency, then the latest, each with why. Expects $failed, $failures, $money, $month and $may. --}}
<div class="card overflow-hidden mb-6">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Failed Payments') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ __('Saved cards the bank refused, or asked the cardholder to confirm.') }}</p>
    </div>

    @if($failed->isEmpty())
        <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ __('No card payment failed in these months.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Month') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th class="text-end">{{ __('Tries') }}</th>
                        <th class="text-end">{{ __('Businesses') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($failed as $row)
                        <tr>
                            <td class="text-sm font-medium">{{ $month($row->month) }}</td>
                            <td class="text-sm">{{ $row->currency }}</td>
                            <td class="text-sm text-end">{{ (int) $row->tries }}</td>
                            <td class="text-sm text-end">{{ (int) $row->businesses }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $money($row->currency, $row->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 pt-5 pb-2">
            <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ __('Latest') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Card') }}</th>
                        <th>{{ __('Why') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($failures as $payment)
                        <tr>
                            <td class="text-sm whitespace-nowrap">{{ ($payment->attempted_on ?? $payment->created_at)->format('M d, Y') }}</td>
                            <td class="text-sm">{{ $payment->workspace?->name ?? __('A deleted business') }}</td>
                            <td class="text-sm">
                                @if($payment->invoice && $may('admin.subscription-invoices.show'))
                                    <a href="{{ route('admin.subscription-invoices.show', $payment->subscription_invoice_id) }}" class="font-medium text-[var(--primary)]">{{ $payment->invoice->number }}</a>
                                @else
                                    {{ $payment->invoice?->number ?? '—' }}
                                @endif
                            </td>
                            <td class="text-sm">{{ $payment->method() }}</td>
                            <td class="text-sm text-[var(--text-secondary)]">{{ $payment->note ?: ($payment->status === 'action_required' ? __('The cardholder must confirm it.') : __('Declined.')) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
