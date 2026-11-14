{{-- izSuite's own billing in the chosen days (Doc 15 section 15.4), by currency and never mixed: invoices paid, refunds, what is left, and card payments that did not go through. For admins who may open Billing Reports. --}}
<div class="card overflow-hidden mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="section-title">{{ __('Subscription Billing') }}</h3>
        <a href="{{ route('admin.billing-reports.index') }}" class="text-sm font-medium text-[var(--primary)]">{{ __('Open Billing Reports') }}</a>
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Currency') }}</th>
                    <th class="text-end">{{ __('Invoices paid') }}</th>
                    <th class="text-end">{{ __('Collected') }}</th>
                    <th class="text-end">{{ __('Of it tax') }}</th>
                    <th class="text-end">{{ __('Refunded') }}</th>
                    <th class="text-end">{{ __('Revenue') }}</th>
                    <th class="text-end">{{ __('Failed card payments') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($billing as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['currency'] }}</td>
                        <td class="text-end">{{ number_format($row['invoices']) }}</td>
                        <td class="text-end whitespace-nowrap">{{ number_format((float) $row['collected'], 2) }}</td>
                        <td class="text-end whitespace-nowrap text-[var(--text-muted)]">{{ number_format((float) $row['tax'], 2) }}</td>
                        <td class="text-end whitespace-nowrap">{{ number_format((float) $row['refunded'], 2) }}</td>
                        <td class="text-end whitespace-nowrap font-semibold">{{ number_format((float) $row['net'], 2) }}</td>
                        <td class="text-end whitespace-nowrap {{ $row['failed_tries'] > 0 ? 'text-[var(--danger)]' : '' }}">
                            {{ number_format((float) $row['failed_amount'], 2) }}
                            <span class="block text-xs text-[var(--text-muted)]">{{ trans_choice(':count try|:count tries', $row['failed_tries']) }}, {{ trans_choice(':count business|:count businesses', $row['failed_businesses']) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-6 text-[var(--text-muted)]">{{ __('Nothing paid, refunded or refused in these days.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs mt-3 text-[var(--text-muted)]">{{ __('Collected is what the invoices paid in these days came to, tax included; revenue is that less the refunds made in them.') }}</p>
</div>
