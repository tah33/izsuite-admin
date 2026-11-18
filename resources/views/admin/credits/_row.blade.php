{{-- One movement of izSuite's credit (Doc 15 Phase C2b): when, whose, what happened, how much, for which invoice, and why. Expects $credit and $may. --}}
@php
    $badge = [
        'granted'  => ['badge-success', __('Given')],
        'used'     => ['badge-inactive', __('Taken off an invoice')],
        'returned' => ['badge-warning', __('Given back')],
        'removed'  => ['badge-danger', __('Taken back')],
    ][$credit->kind] ?? ['badge-inactive', $credit->kind];
    $amount = (float) $credit->amount;
@endphp
<tr>
    <td class="text-sm whitespace-nowrap">
        {{ $credit->created_at->format('M d, Y') }}
        <span class="block text-xs text-[var(--text-muted)]">{{ $credit->created_at->format('H:i') }}</span>
    </td>
    <td>
        <span class="font-semibold text-[var(--text-primary)]">{{ $credit->workspace?->name ?? __('A deleted business') }}</span>
        <span class="block text-xs text-[var(--text-muted)]">{{ $credit->workspace?->businessId() }}{{ $credit->workspace?->user ? ' · '.$credit->workspace->user->email : '' }}</span>
    </td>
    <td class="whitespace-nowrap"><span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span></td>
    <td class="text-sm whitespace-nowrap font-semibold {{ $amount < 0 ? 'text-[var(--text-secondary)]' : 'text-[var(--text-primary)]' }}">
        {{ $amount < 0 ? '−' : '+' }}{{ $credit->currency }} {{ number_format(abs($amount), 2) }}
    </td>
    <td class="text-sm whitespace-nowrap">
        @if($credit->invoice && $may('admin.subscription-invoices.show'))
            <a href="{{ route('admin.subscription-invoices.show', $credit->subscription_invoice_id) }}" class="font-medium text-[var(--primary)]">{{ $credit->invoice->number }}</a>
        @else
            <span class="text-[var(--text-muted)]">{{ $credit->invoice?->number ?? '—' }}</span>
        @endif
    </td>
    <td class="text-sm">
        {{ $credit->reason ?? ($credit->kind === 'used' ? __('Taken off before tax') : __('Its invoice was cancelled')) }}
        @if($credit->creator)
            <span class="block text-xs text-[var(--text-muted)]">{{ __('By :name', ['name' => $credit->creator->name]) }}</span>
        @endif
    </td>
</tr>
