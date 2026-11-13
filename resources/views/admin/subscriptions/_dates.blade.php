{{-- Where a subscription stands in time (Doc 15 section 15.13): its trial, paid period, grace or end. Expects $subscription, with open_invoice_due_on. --}}
@switch($subscription->status)
    @case(\App\Models\Billing\WorkspaceSubscription::TRIALING)
        {{ __('Trial :from to :to', ['from' => $subscription->trial_starts_on?->format('M d'), 'to' => $subscription->trial_ends_on?->format('M d, Y')]) }}
        @break
    @case(\App\Models\Billing\WorkspaceSubscription::AWAITING_PAYMENT)
        {{ $subscription->open_invoice_due_on ? __('First invoice due :date', ['date' => \Illuminate\Support\Carbon::parse($subscription->open_invoice_due_on)->format('M d, Y')]) : __('Waiting for its first payment') }}
        @break
    @case(\App\Models\Billing\WorkspaceSubscription::ACTIVE)
        {{ __('Paid :from to :to', ['from' => $subscription->period_starts_on?->format('M d'), 'to' => $subscription->period_ends_on?->format('M d, Y')]) }}
        <span class="block">{{ $subscription->cancels_at_period_end ? __('Ends after :date', ['date' => $subscription->period_ends_on?->format('M d, Y')]) : __('Renews :date', ['date' => $subscription->period_ends_on?->copy()->addDay()->format('M d, Y')]) }}</span>
        @break
    @case(\App\Models\Billing\WorkspaceSubscription::GRACE)
        {{ __('Unpaid since :date', ['date' => $subscription->period_ends_on?->copy()->addDay()->format('M d, Y')]) }}
        <span class="block text-[var(--danger)]">{{ __('Grace until :date', ['date' => $subscription->grace_ends_on?->format('M d, Y')]) }}</span>
        @break
    @default
        {{ __('Ended :date', ['date' => $subscription->updated_at?->format('M d, Y')]) }}
@endswitch
