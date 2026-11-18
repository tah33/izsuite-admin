{{-- The subscription's history (Doc 15 section 15.13): its business's latest invoices, each with its card tries. Expects $invoices and $may. --}}
<div class="card overflow-hidden mb-6">
    <div class="p-6 pb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Invoices') }}</h2>
        @if($invoices->isNotEmpty() && $may('admin.subscription-invoices.index'))
            <a href="{{ route('admin.subscription-invoices.index', ['search' => $subscription->workspace?->name]) }}" class="btn btn-secondary btn-sm">{{ __('All Invoices') }}</a>
        @endif
    </div>

    @if($invoices->isEmpty())
        <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ __('None yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('For') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Payments') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        @php($lastTry = $invoice->payments->sortByDesc('id')->first())
                        <tr>
                            <td class="text-sm">
                                @if($may('admin.subscription-invoices.show'))
                                    <a href="{{ route('admin.subscription-invoices.show', $invoice->id) }}" class="font-medium text-[var(--primary)]">{{ $invoice->number }}</a>
                                @elseif($may('admin.subscription-invoices.pdf'))
                                    <a href="{{ route('admin.subscription-invoices.pdf', $invoice->id) }}" target="_blank" class="font-medium text-[var(--primary)]">{{ $invoice->number }}</a>
                                @else
                                    <span class="font-medium">{{ $invoice->number }}</span>
                                @endif
                                <span class="block text-xs text-[var(--text-muted)]">{{ __('Issued :date', ['date' => $invoice->issued_on?->format('M d, Y')]) }}</span>
                            </td>
                            <td class="text-sm">
                                {{ $invoice->package_name }}, {{ __($invoice->cycle) }} · {{ __(ucfirst($invoice->kind)) }}
                                @if($invoice->period_starts_on)
                                    <span class="block text-xs text-[var(--text-muted)]">{{ $invoice->period_starts_on->format('M d') }} – {{ $invoice->period_ends_on?->format('M d, Y') }}</span>
                                @endif
                            </td>
                            <td class="text-sm whitespace-nowrap">{{ $invoice->currency }} {{ $invoice->total }}</td>
                            <td class="text-sm">
                                <span class="badge {{ ['open' => 'badge-warning', 'paid' => 'badge-success'][$invoice->status] ?? 'badge-inactive' }}">{{ __(ucfirst($invoice->status)) }}</span>
                                <span class="block text-xs text-[var(--text-muted)] mt-1">
                                    {{ $invoice->paid_at ? __('Paid :date', ['date' => $invoice->paid_at->format('M d, Y')]) : ($invoice->status === 'open' ? __('Due :date', ['date' => $invoice->due_on?->format('M d, Y')]) : '') }}
                                </span>
                            </td>
                            <td class="text-xs">
                                @if($lastTry)
                                    {{-- A transfer izSuite confirmed is a payment too (Phase C2a): how each was paid, not only cards. --}}
                                    @if($invoice->payments->count() > 1)
                                        <span class="block">{{ trans_choice(':count try|:count tries', $invoice->payments->count()) }}</span>
                                    @endif
                                    <span class="block {{ $lastTry->succeeded() && ! $lastTry->notApplied() ? 'text-[var(--text-muted)]' : 'text-[var(--danger)]' }}">{{ $lastTry->succeeded() && ! $lastTry->notApplied() ? __(':method, :date', ['method' => $lastTry->method(), 'date' => $lastTry->attempted_on?->format('M d')]) : ($lastTry->note ?: __(ucfirst(str_replace('_', ' ', $lastTry->status)))) }}</span>
                                @else
                                    <span class="text-[var(--text-muted)]">{{ __('None') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
