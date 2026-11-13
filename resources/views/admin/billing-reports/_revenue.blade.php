{{-- Revenue by month, then by plan, each by currency (Doc 15 section 14): invoices paid, collected, the tax in it, refunded, and kept - the span's totals under each. Expects $months, $plans, $totals, $money and $month. --}}
@foreach([['title' => __('Revenue by Month'), 'rows' => $months, 'totals' => $totals, 'key' => 'month'], ['title' => __('Revenue by Plan'), 'rows' => $plans, 'totals' => $planTotals, 'key' => 'plan']] as $table)
    <div class="card overflow-hidden mb-6">
        <div class="p-6 pb-3">
            <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ $table['title'] }}</h2>
            @if($loop->first)
                <p class="text-sm text-[var(--text-muted)]">{{ __('Invoices count in the month they were paid, refunds in the month the provider confirmed them. Kept is collected less refunded; the tax is part of what was collected.') }}</p>
            @endif
        </div>

        @if($table['rows']->isEmpty())
            <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ __('Nothing paid or refunded in these months.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ $table['key'] === 'month' ? __('Month') : __('Plan') }}</th>
                            <th>{{ __('Currency') }}</th>
                            <th class="text-end">{{ __('Invoices Paid') }}</th>
                            <th class="text-end">{{ __('Collected') }}</th>
                            <th class="text-end">{{ __('Of It Tax') }}</th>
                            <th class="text-end">{{ __('Refunded') }}</th>
                            <th class="text-end">{{ __('Kept') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($table['rows'] as $row)
                            <tr>
                                <td class="text-sm font-medium">{{ $table['key'] === 'month' ? $month($row['month']) : $row['plan'] }}</td>
                                <td class="text-sm">{{ $row['currency'] }}</td>
                                <td class="text-sm text-end">{{ $row['invoices'] }}</td>
                                <td class="text-sm text-end whitespace-nowrap">{{ $money($row['currency'], $row['collected']) }}</td>
                                <td class="text-sm text-end whitespace-nowrap text-[var(--text-muted)]">{{ $money($row['currency'], $row['tax']) }}</td>
                                <td class="text-sm text-end whitespace-nowrap">{{ (float) $row['refunded'] > 0 ? '− '.$money($row['currency'], $row['refunded']) : '—' }}</td>
                                <td class="text-sm text-end whitespace-nowrap font-semibold">{{ $money($row['currency'], $row['kept']) }}</td>
                            </tr>
                        @endforeach
                        @foreach($table['totals'] as $total)
                            <tr class="font-semibold">
                                <td class="text-sm">{{ __('Total') }}</td>
                                <td class="text-sm">{{ $total['currency'] }}</td>
                                <td class="text-sm text-end">{{ $total['invoices'] }}</td>
                                @foreach(['collected', 'tax', 'refunded', 'kept'] as $column)
                                    <td class="text-sm text-end whitespace-nowrap">{{ $money($total['currency'], $total[$column]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endforeach
