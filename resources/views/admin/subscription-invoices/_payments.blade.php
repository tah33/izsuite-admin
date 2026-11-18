{{-- An invoice's payments (Doc 15 section 14 - Phase C2a): a card's, the saved card's tries and a transfer izSuite confirmed, each with its refunds - and Refund while some is left. Expects $invoice, $refundable, $money and $may. --}}
<div class="card overflow-hidden mb-6">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Payments') }}</h2>
    </div>

    @if($invoice->payments->isEmpty())
        <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ $invoice->isOpen() ? __('None yet: it waits for a bank transfer or a card payment.') : __('None.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Paid By') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Refunds') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments as $payment)
                        @php
                            $done = $payment->refunds->where('status', 'succeeded')->reduce(fn (string $sum, $refund) => bcadd($sum, (string) $refund->amount, 2), '0.00');
                            $left = $refundable[$payment->id] ?? '0.00';
                        @endphp
                        <tr>
                            <td class="text-sm whitespace-nowrap">{{ ($payment->attempted_on ?? $payment->created_at)->format('M d, Y') }}</td>
                            <td class="text-sm">
                                {{ $payment->method() }}
                                <span class="block text-xs text-[var(--text-muted)] break-words">{{ $payment->isTransfer() ? ($invoice->transfer_reference ? __('Reference :ref', ['ref' => $invoice->transfer_reference]) : __('Confirmed by izSuite')) : $payment->reference }}</span>
                                @if($payment->note)
                                    <span class="block text-xs {{ $payment->succeeded() ? 'text-[var(--danger)]' : 'text-[var(--text-muted)]' }}">{{ $payment->note }}</span>
                                @endif
                            </td>
                            <td class="text-sm whitespace-nowrap font-semibold">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="whitespace-nowrap">@include('admin.payments._status', ['refunded' => $done, 'refundPending' => $payment->refunds->contains('status', 'pending')])</td>
                            <td class="text-xs">
                                @forelse($payment->refunds as $refund)
                                    <span class="block mb-1">
                                        <span class="font-semibold">{{ $refund->currency }} {{ number_format((float) $refund->amount, 2) }}</span>
                                        · {{ ['pending' => __('waiting for the provider'), 'succeeded' => __('refunded :date', ['date' => $refund->confirmed_at?->format('M d')]), 'failed' => __('failed')][$refund->status] ?? $refund->status }}
                                        · {{ $refund->refunder?->name ?? __('in the provider\'s dashboard') }}
                                        <span class="block text-[var(--text-muted)] break-words">{{ $refund->reason }}{{ $refund->note ? ' '.$refund->note : '' }}{{ ($refund->transfer_reference ?? $refund->reference) ? ' ('.($refund->transfer_reference ?? $refund->reference).')' : '' }}</span>
                                    </span>
                                @empty
                                    <span class="text-[var(--text-muted)]">{{ __('None') }}</span>
                                @endforelse
                            </td>
                            <td class="text-end">
                                @if(bccomp($left, '0', 2) > 0 && $may('admin.payments.refund'))
                                    <button type="button" class="btn btn-xs btn-secondary" data-modal-open="refund-payment-{{ $payment->id }}"><i data-lucide="undo-2" class="w-3.5 h-3.5"></i>{{ __('Refund') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
