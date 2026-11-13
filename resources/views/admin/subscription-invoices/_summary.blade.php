{{-- An invoice's fields as Doc 15 section 14 names them: who it bills, what and when, and its amounts - paid, refunded and still due. Expects $invoice, $paid, $refunded, $balance and $money. --}}
@php
    $billed = $invoice->billed_to ?? [];
    $rows   = array_filter([
        __('Subtotal')     => $money($invoice->amount),
        __('Credit for the unused plan') => (float) $invoice->credit > 0 ? '− '.$money($invoice->credit) : null,
        __('Tax')          => $invoice->tax_name
            ? ($invoice->tax_note ? __(':tax by reverse charge', ['tax' => $invoice->tax_name]) : __(':tax :rate%: :amount', ['tax' => $invoice->tax_name, 'rate' => $invoice->taxPercent(), 'amount' => $money($invoice->tax)]))
            : __('None'),
        __('Total')        => $money($invoice->total),
        __('Amount paid')  => $money($paid),
        __('Refunded')     => bccomp($refunded, '0', 2) > 0 ? $money($refunded) : null,
        __('Balance')      => $money($balance),
    ]);
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="card lg:col-span-2">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-xs text-[var(--text-muted)]">{{ __('Billed to') }}</dt>
                <dd class="font-medium">
                    {{ $billed['name'] ?? $invoice->workspace?->name }}
                    @foreach(array_filter([$billed['address'] ?? null, $billed['country'] ?? null, isset($billed['tax_id']) ? __('Tax ID :id', ['id' => $billed['tax_id']]) : null, $billed['email'] ?? null]) as $line)
                        <span class="block font-normal text-[var(--text-secondary)] whitespace-pre-line">{{ $line }}</span>
                    @endforeach
                </dd>
            </div>
            <div>
                <dt class="text-xs text-[var(--text-muted)]">{{ __('Business') }}</dt>
                <dd class="font-medium">
                    {{ $invoice->workspace?->name ?? __('A deleted business') }} · {{ $invoice->workspace?->businessId() }}
                    <span class="block font-normal text-[var(--text-secondary)]">{{ $invoice->workspace?->user?->name }} · {{ $invoice->workspace?->user?->email }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-[var(--text-muted)]">{{ __('Plan') }}</dt>
                <dd class="font-medium">{{ $invoice->package_name }}, {{ __($invoice->cycle) }} · {{ __(ucfirst($invoice->kind)) }} · {{ $invoice->currency }}</dd>
            </div>
            <div>
                <dt class="text-xs text-[var(--text-muted)]">{{ __('Billing period') }}</dt>
                <dd class="font-medium">{{ $invoice->period_starts_on ? $invoice->period_starts_on->format('M d, Y').' – '.$invoice->period_ends_on?->format('M d, Y') : __('Starts when it is paid') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-[var(--text-muted)]">{{ __('Issued') }}</dt>
                <dd class="font-medium">{{ $invoice->issued_on->format('M d, Y') }} · {{ __('due :date', ['date' => $invoice->due_on->format('M d, Y')]) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-[var(--text-muted)]">{{ $invoice->isPaid() ? __('Paid') : ($invoice->voided_at ? __('Cancelled') : __('Transfer reference')) }}</dt>
                <dd class="font-medium">
                    @if($invoice->isPaid())
                        {{ $invoice->paid_at?->format('M d, Y') }}{{ $invoice->paid_with ? ' · '.$invoice->paid_with : '' }}{{ $invoice->confirmer ? ' · '.__('confirmed by :name', ['name' => $invoice->confirmer->name]) : '' }}
                    @elseif($invoice->voided_at)
                        {{ $invoice->voided_at->format('M d, Y') }}
                    @else
                        {{ $invoice->transfer_reference ?: __('None given yet') }}
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="card">
        <dl class="space-y-2 text-sm">
            @foreach($rows as $label => $value)
                <div class="flex justify-between gap-4 {{ in_array($label, [__('Total'), __('Balance')], true) ? 'font-semibold text-[var(--text-primary)] border-t border-[var(--card-border)] pt-2' : '' }}">
                    <dt class="text-[var(--text-secondary)]">{{ $label }}</dt>
                    <dd class="text-end whitespace-nowrap">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
