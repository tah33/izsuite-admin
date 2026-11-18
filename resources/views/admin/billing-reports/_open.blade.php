{{-- Invoices waiting for payment now, by currency - and of them, those past their due day, the longest overdue listed (Doc 15 section 14). Expects $open, $overdue, $money and $may. --}}
<div class="card overflow-hidden mb-6">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Open and Overdue Invoices') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ __('As they stand today, whatever the months chosen.') }}</p>
    </div>

    @if($open->isEmpty())
        <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ __('No invoice is waiting for payment.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Currency') }}</th>
                        <th class="text-end">{{ __('Open') }}</th>
                        <th class="text-end">{{ __('Owed') }}</th>
                        <th class="text-end">{{ __('Overdue') }}</th>
                        <th class="text-end">{{ __('Overdue Owed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($open as $row)
                        <tr>
                            <td class="text-sm font-medium">{{ $row->currency }}</td>
                            <td class="text-sm text-end">{{ (int) $row->invoices }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $money($row->currency, $row->owed) }}</td>
                            <td class="text-sm text-end {{ (int) $row->overdue > 0 ? 'text-[var(--danger)] font-semibold' : '' }}">{{ (int) $row->overdue }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $money($row->currency, $row->overdue_owed) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($overdue->isNotEmpty())
            <div class="px-6 pt-5 pb-2">
                <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ __('Longest Overdue') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice') }}</th>
                            <th>{{ __('Business') }}</th>
                            <th class="text-end">{{ __('Total') }}</th>
                            <th>{{ __('Due') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($overdue as $invoice)
                            <tr>
                                <td class="text-sm">
                                    @if($may('admin.subscription-invoices.show'))
                                        <a href="{{ route('admin.subscription-invoices.show', $invoice->id) }}" class="font-medium text-[var(--primary)]">{{ $invoice->number }}</a>
                                    @else
                                        <span class="font-medium">{{ $invoice->number }}</span>
                                    @endif
                                    <span class="block text-xs text-[var(--text-muted)]">{{ $invoice->package_name }}</span>
                                </td>
                                <td class="text-sm">{{ $invoice->workspace?->name ?? __('A deleted business') }}</td>
                                <td class="text-sm text-end whitespace-nowrap">{{ $money($invoice->currency, $invoice->total) }}</td>
                                <td class="text-sm whitespace-nowrap text-[var(--danger)]">{{ $invoice->due_on->format('M d, Y') }} · {{ trans_choice(':count day late|:count days late', (int) $invoice->due_on->diffInDays(today())) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
