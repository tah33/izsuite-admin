{{-- One subscription in Admin > Subscriptions (Doc 15 section 15.13). Expects $subscription, with SubscriptionAdminRepository's facts. --}}
<tr>
    <td>
        <a href="{{ route('admin.subscriptions.show', $subscription->id) }}" class="font-semibold text-[var(--primary)]">{{ $subscription->workspace?->name ?? __('A deleted business') }}</a>
        <span class="block text-xs text-[var(--text-muted)]">{{ $subscription->workspace?->businessId() }} · {{ $subscription->workspace?->user?->email }}</span>
    </td>
    <td class="text-sm">
        {{ $subscription->package?->name }}
        <span class="block text-xs text-[var(--text-muted)]">{{ __(ucfirst($subscription->cycle)) }} · {{ $subscription->price !== null ? $subscription->paysIn().' '.$subscription->price : $subscription->paysIn() }}</span>
        @if($subscription->scheduledPackage)
            <span class="block text-xs text-[var(--warning)]">{{ __('Moving to :plan', ['plan' => $subscription->scheduledPackage->name]) }}</span>
        @endif
    </td>
    <td class="whitespace-nowrap">@include('admin.subscriptions._status-badge')</td>
    <td class="text-xs text-[var(--text-muted)] whitespace-nowrap">@include('admin.subscriptions._dates')</td>
    <td class="text-xs whitespace-nowrap">
        <span class="block text-[var(--text-primary)]">{{ $subscription->card_last4 ? \Illuminate\Support\Str::title(str_replace('_', ' ', (string) $subscription->card_brand)).' •••• '.$subscription->card_last4 : __('Bank transfer') }}</span>
        @if($subscription->last_try_status && $subscription->last_try_status !== \App\Models\Billing\SubscriptionPayment::SUCCEEDED)
            <span class="block text-[var(--danger)] font-medium">{{ __('Last card try did not go through') }}</span>
        @elseif($subscription->last_paid_at)
            <span class="block text-[var(--text-muted)]">{{ __('Last paid :date', ['date' => \Illuminate\Support\Carbon::parse($subscription->last_paid_at)->format('M d, Y')]) }}</span>
        @else
            <span class="block text-[var(--text-muted)]">{{ __('Not paid yet') }}</span>
        @endif
    </td>
</tr>
