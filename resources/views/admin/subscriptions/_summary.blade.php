{{-- Where the subscription stands (Doc 15 section 15.13): plan, dates, a change waiting, how it is paid, and its invoice waiting for payment. --}}
<div class="card mb-6">
    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Plan') }}</dt>
            <dd class="font-medium">{{ $subscription->package?->name }}, {{ __($subscription->cycle) }} · {{ $subscription->package?->price_currency }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Dates') }}</dt>
            <dd class="font-medium">@include('admin.subscriptions._dates')</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Waiting for the period\'s end') }}</dt>
            <dd class="font-medium">
                @if($subscription->cancels_at_period_end)
                    {{ __('Cancellation: the business moves to Billing Starter') }}
                @elseif($subscription->scheduledPackage)
                    {{ __('A move to :plan, :cycle', ['plan' => $subscription->scheduledPackage->name, 'cycle' => __($subscription->scheduled_cycle)]) }}
                @else
                    {{ __('Nothing') }}
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Paid by') }}</dt>
            <dd class="font-medium">{{ $card ? $card->label() : __('Bank transfer (no card saved)') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Invoice waiting for payment') }}</dt>
            <dd class="font-medium">
                {{ $openInvoice
                    ? __(':number, :currency :total, due :date', ['number' => $openInvoice->number, 'currency' => $openInvoice->currency, 'total' => $openInvoice->total, 'date' => $openInvoice->due_on->format('M d, Y')])
                    : __('None') }}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('izSuite credit') }}</dt>
            <dd class="font-medium">{{ bccomp($credit, '0', 2) > 0 ? __(':currency :amount, to come off its next invoices', ['currency' => $subscription->package?->price_currency, 'amount' => $credit]) : __('None') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Owner') }}</dt>
            <dd class="font-medium">{{ $subscription->workspace?->user?->name }} · {{ $subscription->workspace?->user?->email }}</dd>
        </div>
    </dl>
</div>
