{{-- A subscription's status as a badge (Doc 15 section 15.13), and its business's own status beside it when that is not Active. Expects $subscription. --}}
@php($key = \App\Support\SubscriptionStatus::of($subscription))
<span class="badge {{ [
    'trialing'               => 'badge-trial',
    'awaiting_payment'       => 'badge-warning',
    'active'                 => 'badge-success',
    'past_due'               => 'badge-danger',
    'cancellation_scheduled' => 'badge-warning',
    'downgrade_scheduled'    => 'badge-warning',
    'grace'                  => 'badge-danger',
][$key] ?? 'badge-inactive' }}">{{ __(\App\Support\SubscriptionStatus::label($subscription)) }}</span>
@if($subscription->workspace && $subscription->workspace->status !== \App\Support\BusinessStatus::ACTIVE)
    @include('admin.businesses._status-badge', ['status' => $subscription->workspace->status])
@endif
