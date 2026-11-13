{{-- One refund (Doc 15 Phase C2a): when, whose, of which payment, how much, where it stands, and why and by whom. Expects $refund and $may. --}}
@php
    $badge = ['pending' => ['badge-warning', __('Waiting for the provider')], 'succeeded' => ['badge-success', __('Refunded')], 'failed' => ['badge-danger', __('Failed')]][$refund->status] ?? ['badge-inactive', $refund->status];
@endphp
<tr>
    <td class="text-sm whitespace-nowrap">
        {{ $refund->created_at->format('M d, Y') }}
        <span class="block text-xs text-[var(--text-muted)]">{{ $refund->confirmed_at ? __('Confirmed :date', ['date' => $refund->confirmed_at->format('M d, H:i')]) : $refund->created_at->format('H:i') }}</span>
    </td>
    <td>
        <span class="font-semibold text-[var(--text-primary)]">{{ $refund->workspace?->name ?? __('A deleted business') }}</span>
        <span class="block text-xs text-[var(--text-muted)]">{{ $refund->workspace?->businessId() }}{{ $refund->workspace?->user ? ' · '.$refund->workspace->user->email : '' }}</span>
    </td>
    <td class="text-sm">
        @if($refund->invoice && $may('admin.subscription-invoices.show'))
            <a href="{{ route('admin.subscription-invoices.show', $refund->subscription_invoice_id) }}" class="font-medium text-[var(--primary)]">{{ $refund->invoice->number }}</a>
        @else
            <span class="font-medium">{{ $refund->invoice?->number ?? '—' }}</span>
        @endif
        <span class="block text-xs text-[var(--text-muted)]">{{ $refund->payment?->method() }}</span>
        <span class="block text-xs text-[var(--text-muted)] break-words">{{ $refund->reference ?? $refund->transfer_reference }}</span>
    </td>
    <td class="text-sm whitespace-nowrap font-semibold">{{ $refund->currency }} {{ number_format((float) $refund->amount, 2) }}</td>
    <td class="text-sm">
        <span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span>
        @if($refund->note)
            <span class="block text-xs text-[var(--danger)] mt-1">{{ $refund->note }}</span>
        @endif
    </td>
    <td class="text-sm">
        {{ $refund->reason }}
        <span class="block text-xs text-[var(--text-muted)]">{{ $refund->refunder ? __('By :name', ['name' => $refund->refunder->name]) : __('In the provider\'s dashboard') }}</span>
    </td>
</tr>
