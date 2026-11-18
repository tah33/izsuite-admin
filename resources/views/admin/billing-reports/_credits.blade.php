{{-- izSuite's credit to businesses by currency (Doc 15 section 14): given and taken off invoices in these months, and what is left to come off now. Expects $credits and $money. --}}
<div class="card overflow-hidden mb-6">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Credits') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ __('izSuite\'s credit to businesses. What is left now comes off their next invoices.') }}</p>
    </div>

    @if($credits->isEmpty())
        <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ __('No credit given yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Currency') }}</th>
                        <th class="text-end">{{ __('Given') }}</th>
                        <th class="text-end">{{ __('Taken Off Invoices') }}</th>
                        <th class="text-end">{{ __('Left Now') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($credits as $row)
                        <tr>
                            <td class="text-sm font-medium">{{ $row->currency }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $money($row->currency, $row->granted) }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $money($row->currency, $row->used) }}</td>
                            <td class="text-sm text-end whitespace-nowrap font-semibold">{{ $money($row->currency, $row->outstanding) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
