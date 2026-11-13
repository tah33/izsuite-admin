{{-- The dialogs behind _actions' buttons, each only where its button shows. Every change asks why; the owner is told. --}}
@php
    $state = \App\Support\SubscriptionStatus::of($subscription);
    $plan  = $subscription->package?->name;
@endphp

@if($subscription->status === 'active' && $may('admin.subscriptions.plan'))
    @include('admin.partials.reason-modal', [
        'id'     => 'subscription-plan-modal',
        'title'  => __('Change Plan'),
        'text'   => __('As the business itself would: a higher plan, or monthly to yearly, issues its invoice now and starts once it is paid; a lower one waits for the paid period\'s end. The owner is told.'),
        'route'  => route('admin.subscriptions.plan', $subscription->id),
        'button' => __('Change Plan'),
        'fields' => 'admin.subscriptions._plan-fields',
    ])
@endif

@if($subscription->status === 'trialing' && $may('admin.subscriptions.trial'))
    @include('admin.partials.reason-modal', [
        'id'     => 'subscription-trial-modal',
        'title'  => __('Extend the Trial'),
        'text'   => __('The trial ends on :date. Extended, its invoice is issued again, due on the new last day. The owner is told.', ['date' => $subscription->trial_ends_on?->format('M d, Y')]),
        'route'  => route('admin.subscriptions.trial', $subscription->id),
        'button' => __('Extend Trial'),
        'fields' => 'admin.subscriptions._days-field',
    ])
@endif

@if($subscription->status === 'grace' && $may('admin.subscriptions.grace'))
    @include('admin.partials.reason-modal', [
        'id'     => 'subscription-grace-modal',
        'title'  => __('Extend the Grace Period'),
        'text'   => __(':plan stays, unpaid, until :date. More days give the business longer to pay before it moves to Billing Starter. The owner is told.', ['plan' => $plan, 'date' => $subscription->grace_ends_on?->format('M d, Y')]),
        'route'  => route('admin.subscriptions.grace', $subscription->id),
        'button' => __('Extend Grace'),
        'fields' => 'admin.subscriptions._days-field',
    ])
@endif

@if(in_array($state, ['cancellation_scheduled', 'downgrade_scheduled'], true) && $may('admin.subscriptions.undo'))
    @include('admin.partials.reason-modal', [
        'id'     => 'subscription-undo-modal',
        'title'  => __('Undo the Scheduled Change'),
        'text'   => $state === 'cancellation_scheduled'
            ? __(':plan carries on instead of ending with its paid period, and renews as before. The owner is told.', ['plan' => $plan])
            : __('The business stays on :plan instead of moving to :to. The owner is told.', ['plan' => $plan, 'to' => $subscription->scheduledPackage?->name]),
        'route'  => route('admin.subscriptions.undo', $subscription->id),
        'button' => __('Undo It'),
    ])
@endif

@if($may('admin.subscriptions.credit'))
    @include('admin.partials.reason-modal', [
        'id'       => 'subscription-credit-modal',
        'title'    => __('Give a Credit'),
        'text'     => __('It comes off :next, before tax; what is left comes off the ones after. The owner is told.', ['next' => $openInvoice ? __('the invoice after :number (that one is waiting for payment now)', ['number' => $openInvoice->number]) : __('the business\'s next invoice')]),
        'route'    => route('admin.subscriptions.credit', $subscription->id),
        'button'   => __('Give Credit'),
        'fields'   => 'admin.subscriptions._credit-field',
        'currency' => $subscription->package?->price_currency,
        'most'     => null,
    ])
@endif

@if(bccomp($credit, '0', 2) > 0 && $may('admin.subscriptions.credit-remove'))
    @include('admin.partials.reason-modal', [
        'id'       => 'subscription-credit-remove-modal',
        'title'    => __('Take Back Credit'),
        'text'     => __('Takes back credit not used yet; what an invoice took stays on it. The owner is told.'),
        'route'    => route('admin.subscriptions.credit-remove', $subscription->id),
        'button'   => __('Take Back'),
        'danger'   => true,
        'fields'   => 'admin.subscriptions._credit-field',
        'currency' => $subscription->package?->price_currency,
        'most'     => $credit,
    ])
@endif

@if(in_array($state, ['active', 'past_due'], true) && $may('admin.subscriptions.cancel'))
    @include('admin.partials.reason-modal', [
        'id'     => 'subscription-cancel-modal',
        'title'  => __('Schedule the Cancellation'),
        'text'   => __(':plan stays until :date, then the business moves to Billing Starter, the free plan, and keeps its records. Its renewal, if issued, is cancelled. The owner is told.', ['plan' => $plan, 'date' => $subscription->period_ends_on?->format('M d, Y')]),
        'route'  => route('admin.subscriptions.cancel', $subscription->id),
        'button' => __('Schedule Cancellation'),
        'danger' => true,
    ])
@endif
