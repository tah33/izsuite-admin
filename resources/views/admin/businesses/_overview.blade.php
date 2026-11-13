{{-- A business's profile, owner, plan and subscription, and billing details (Doc 15 section 15.5). --}}
@php
    $profile      = $business->businessSetting;
    $owner        = $business->user;
    $subscription = $business->subscription;
    $billing      = $business->billing;
    $cards        = [
        __('Business Profile') => [
            __('Legal name')    => $profile?->business_name,
            __('Trading name')  => $profile?->trading_name,
            __('Industry')      => $profile?->industry,
            __('Country')       => \App\Support\Countries::name($profile?->country),
            __('E-mail')        => $profile?->email,
            __('Phone')         => $profile?->business_contact_number,
            __('Address')       => collect([$profile?->address_line_1, $profile?->city])->filter()->implode(', '),
        ],
        __('Owner') => [
            __('Name')           => $owner?->name,
            __('E-mail')         => $owner?->email,
            __('E-mail checked') => $owner ? ($owner->email_verified_at ? __('Yes') : __('Not yet')) : null,
        ],
        __('Plan and Subscription') => [
            __('Plan')         => $business->package?->name,
            __('Subscription') => __(\App\Services\Admin\BusinessService::SUBSCRIPTION_LABELS[$subscriptionStatus]),
            __('Billed')       => $subscriptionStatus !== 'free' ? ucfirst((string) $subscription?->cycle) : null,
            __('Trial ends')   => $subscriptionStatus === 'trialing' ? $subscription?->trial_ends_on?->format('M d, Y') : null,
            __('Paid until')   => in_array($subscriptionStatus, ['active', 'cancellation_scheduled', 'grace'], true) ? $subscription?->period_ends_on?->format('M d, Y') : null,
            __('Grace ends')   => $subscriptionStatus === 'grace' ? $subscription?->grace_ends_on?->format('M d, Y') : null,
        ],
        __('Billing Details') => [
            __('Billed to') => $billing?->name,
            __('E-mail')    => $billing?->email,
            __('Address')   => $billing?->address,
            __('Country')   => \App\Support\Countries::name($billing?->country),
            __('Tax ID')    => $billing?->tax_id,
        ],
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    @foreach($cards as $title => $facts)
        <div class="card p-6">
            <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">{{ $title }}</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                @foreach(array_filter($facts, fn ($value) => $value !== null && $value !== '') as $label => $value)
                    <div>
                        <dt class="text-[var(--text-muted)] mb-0.5">{{ $label }}</dt>
                        <dd class="font-medium text-[var(--text-primary)] break-words">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if(! array_filter($facts, fn ($value) => $value !== null && $value !== ''))
                <p class="text-sm text-[var(--text-muted)]">{{ __('Nothing given yet.') }}</p>
            @endif
        </div>
    @endforeach
</div>
