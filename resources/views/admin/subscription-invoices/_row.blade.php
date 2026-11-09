{{-- One subscription invoice: who and what it bills, when, and - while it waits for the transfer - confirming or cancelling it. --}}
@php
    $badge = ['open' => ['badge-warning', __('To be paid')], 'paid' => ['badge-success', __('Paid')], 'void' => ['badge-inactive', __('Cancelled')]][$invoice->status] ?? ['badge-inactive', $invoice->status];
@endphp
<tr>
    <td class="whitespace-nowrap">
        <a href="{{ route('admin.subscription-invoices.pdf', $invoice->id) }}" target="_blank" class="font-semibold text-[var(--primary)]">{{ $invoice->number }}</a>
        @if($invoice->receipt_number)
            <a href="{{ route('admin.subscription-invoices.pdf', ['id' => $invoice->id, 'receipt' => 1]) }}" target="_blank" class="block text-xs text-[var(--text-muted)]">{{ __('Receipt') }} {{ $invoice->receipt_number }}</a>
        @endif
    </td>
    <td>
        <span class="font-semibold text-[var(--text-primary)]">{{ $invoice->workspace?->name }}</span>
        @if($invoice->workspace?->user)
            <a href="{{ route('admin.clients.show', $invoice->workspace->user_id) }}" class="block text-xs text-[var(--text-muted)]">{{ $invoice->workspace->user->email }}</a>
        @endif
    </td>
    <td class="text-sm">
        {{ $invoice->package_name }}
        <span class="block text-xs text-[var(--text-muted)]">{{ $invoice->cycle === 'yearly' ? __('Yearly') : __('Monthly') }}{{ ['renewal' => ' · '.__('Renewal'), 'upgrade' => ' · '.__('Upgrade')][$invoice->kind] ?? '' }}</span>
    </td>
    <td class="text-sm whitespace-nowrap font-semibold">
        {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}
        @if($invoice->tax_name)
            <span class="block text-xs font-normal text-[var(--text-muted)]">{{ $invoice->tax_note ? __(':tax by reverse charge', ['tax' => $invoice->tax_name]) : __('incl. :tax :rate%: :amount', ['tax' => $invoice->tax_name, 'rate' => $invoice->taxPercent(), 'amount' => number_format((float) $invoice->tax, 2)]) }}</span>
        @endif
    </td>
    <td class="text-sm whitespace-nowrap">
        {{ __('Issued') }} {{ $invoice->issued_on->format('M d, Y') }}
        <span class="block text-xs text-[var(--text-muted)]">
            @if($invoice->paid_at)
                {{ __('Paid') }} {{ $invoice->paid_at->format('M d, Y') }}{{ $invoice->paid_with ? ' · '.$invoice->paid_with : '' }}{{ $invoice->confirmer ? ' · '.$invoice->confirmer->name : '' }}
            @else
                {{ __('Due') }} {{ $invoice->due_on->format('M d, Y') }}
            @endif
        </span>
    </td>
    <td class="text-sm">
        {{ $invoice->payment_reference ?: ($invoice->transfer_reference ?: '—') }}
        {{-- The saved card's attempts (Phase D3), and a card payment that could not pay it (Phase D2): to refund in Stripe. --}}
        @foreach($invoice->payments as $payment)
            @continue($payment->succeeded() && ! $payment->note)
            <span class="block text-xs {{ $payment->succeeded() ? 'text-[var(--danger)]' : 'text-[var(--text-muted)]' }}">
                {{ ($payment->attempted_on ?? $payment->created_at)->format('M d') }} · {{ $payment->card }}:
                {{ ['failed' => __('declined'), 'action_required' => __('the cardholder must confirm'), 'processing' => __('processing')][$payment->status] ?? $payment->currency.' '.number_format((float) $payment->amount, 2).' ('.$payment->reference.')' }}{{ $payment->note ? '. '.$payment->note : '' }}
            </span>
        @endforeach
    </td>
    <td class="whitespace-nowrap"><span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span></td>
    <td class="text-end">
        @if($invoice->isOpen())
            <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                <form action="{{ route('admin.subscription-invoices.confirm', $invoice->id) }}" method="POST" onsubmit="return confirm('{{ __('Confirm that :total arrived for :number? This issues the receipt and starts the plan.', ['total' => $invoice->currency.' '.number_format((float) $invoice->total, 2), 'number' => $invoice->number]) }}');">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-primary" data-loading-label="{{ __('Confirming...') }}"><i data-lucide="check" class="w-3.5 h-3.5"></i> {{ __('Confirm payment') }}</button>
                </form>
                <form action="{{ route('admin.subscription-invoices.void', $invoice->id) }}" method="POST" onsubmit="return confirm('{{ __('Cancel :number? It stays in the history and will not be paid.', ['number' => $invoice->number]) }}');">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-secondary">{{ __('Cancel') }}</button>
                </form>
            </div>
        @endif
    </td>
</tr>
