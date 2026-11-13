{{-- izSuite's changes to the subscription (Doc 15 section 15.13), each where its role allows and the subscription's state fits. --}}
@php
    $state    = \App\Support\SubscriptionStatus::of($subscription);
    // Running and not ending: Active, or Past Due - its renewal's card refused while the period runs.
    $active   = in_array($state, [\App\Models\Billing\WorkspaceSubscription::ACTIVE, \App\Support\SubscriptionStatus::PAST_DUE], true);
    $waiting  = in_array($state, [\App\Support\SubscriptionStatus::CANCELLATION_SCHEDULED, \App\Support\SubscriptionStatus::DOWNGRADE_SCHEDULED], true);
@endphp
<div class="flex flex-wrap items-center gap-2 mb-6">
    @if(in_array($subscription->status, ['active'], true) && $may('admin.subscriptions.plan'))
        <button type="button" class="btn btn-primary" data-modal-open="subscription-plan-modal"><i data-lucide="arrow-up-down" class="w-4 h-4"></i>{{ __('Change Plan') }}</button>
    @endif
    @if($subscription->status === 'trialing' && $may('admin.subscriptions.trial'))
        <button type="button" class="btn btn-secondary" data-modal-open="subscription-trial-modal"><i data-lucide="calendar-plus" class="w-4 h-4"></i>{{ __('Extend Trial') }}</button>
    @endif
    @if($subscription->status === 'grace' && $may('admin.subscriptions.grace'))
        <button type="button" class="btn btn-secondary" data-modal-open="subscription-grace-modal"><i data-lucide="calendar-plus" class="w-4 h-4"></i>{{ __('Extend Grace') }}</button>
    @endif
    @if($waiting && $may('admin.subscriptions.undo'))
        <button type="button" class="btn btn-secondary" data-modal-open="subscription-undo-modal"><i data-lucide="undo-2" class="w-4 h-4"></i>{{ __('Undo Scheduled Change') }}</button>
    @endif
    @if($openInvoice || $invoices->isNotEmpty())
        @if($may('admin.subscriptions.resend'))
            {{-- Nothing on the page changes, so it stays, and the message with it. --}}
            <form action="{{ route('admin.subscriptions.resend', $subscription->id) }}" method="POST" data-no-reload>
                @csrf
                <button type="submit" class="btn btn-secondary"><i data-lucide="send" class="w-4 h-4"></i>{{ __('Resend Invoice') }}</button>
            </form>
        @endif
    @endif
    @if($canRetry && $may('admin.subscriptions.retry'))
        <form action="{{ route('admin.subscriptions.retry', $subscription->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-secondary"><i data-lucide="refresh-cw" class="w-4 h-4"></i>{{ __('Retry Card') }}</button>
        </form>
    @endif
    @if($active && $may('admin.subscriptions.cancel'))
        <button type="button" class="btn btn-danger" data-modal-open="subscription-cancel-modal"><i data-lucide="circle-x" class="w-4 h-4"></i>{{ __('Schedule Cancellation') }}</button>
    @endif
    @if($subscription->workspace && $may('admin.businesses.show'))
        <a href="{{ route('admin.businesses.show', $subscription->workspace_id) }}" class="btn btn-secondary ms-auto"><i data-lucide="building-2" class="w-4 h-4"></i>{{ __('The Business') }}</a>
    @endif
</div>
