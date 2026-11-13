{{-- One payment for izSuite's invoice (Doc 15 Phase C2a): when, whose, for which invoice, how, how much, and where it stands. Expects $payment and $may. --}}
<tr>
    <td class="text-sm whitespace-nowrap">
        {{ ($payment->attempted_on ?? $payment->created_at)->format('M d, Y') }}
        <span class="block text-xs text-[var(--text-muted)]">{{ $payment->created_at->format('H:i') }}</span>
    </td>
    <td>
        @if($payment->workspace && $may('admin.businesses.show'))
            <a href="{{ route('admin.businesses.show', $payment->workspace_id) }}" class="font-semibold text-[var(--primary)]">{{ $payment->workspace->name }}</a>
        @else
            <span class="font-semibold text-[var(--text-primary)]">{{ $payment->workspace?->name ?? __('A deleted business') }}</span>
        @endif
        <span class="block text-xs text-[var(--text-muted)]">{{ $payment->workspace?->businessId() }}{{ $payment->workspace?->user ? ' · '.$payment->workspace->user->email : '' }}</span>
    </td>
    <td class="text-sm">
        @if($payment->invoice)
            @if($may('admin.subscription-invoices.show'))
                <a href="{{ route('admin.subscription-invoices.show', $payment->subscription_invoice_id) }}" class="font-medium text-[var(--primary)]">{{ $payment->invoice->number }}</a>
            @else
                <span class="font-medium">{{ $payment->invoice->number }}</span>
            @endif
            <span class="block text-xs text-[var(--text-muted)]">{{ $payment->invoice->package_name }}, {{ __($payment->invoice->cycle) }}</span>
        @else
            <span class="text-[var(--text-muted)]">—</span>
        @endif
    </td>
    <td class="text-sm">
        {{ $payment->method() }}
        <span class="block text-xs text-[var(--text-muted)] break-words">{{ $payment->isTransfer() ? __('Confirmed by izSuite') : $payment->reference }}</span>
        @if($payment->note)
            <span class="block text-xs {{ $payment->succeeded() ? 'text-[var(--danger)]' : 'text-[var(--text-muted)]' }}">{{ $payment->note }}</span>
        @endif
    </td>
    <td class="text-sm whitespace-nowrap font-semibold">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
    <td class="whitespace-nowrap">@include('admin.payments._status', ['refunded' => $payment->refunded, 'refundPending' => $payment->refund_pending])</td>
</tr>
