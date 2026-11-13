@extends('layouts.admin')
@section('title', __('Businesses'))

{{-- Doc 15 section 15.5: every business, one row each. Admin > Users stays the list of people. --}}
@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Businesses') }}</h1>
        <p class="page-subtitle">{{ __('Every business on izSuite: its owner, plan, subscription, status and usage.') }}</p>
    </div>

    @include('admin.businesses._filters')

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Usage') }}</th>
                        <th>{{ __('Signed Up') }}</th>
                        <th>{{ __('Last Activity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($businesses as $business)
                        @php($usage = \App\Support\BusinessUsage::of($business))
                        <tr>
                            <td>
                                <a href="{{ route('admin.businesses.show', $business->id) }}" class="font-semibold text-[var(--primary)]">{{ $business->name }}</a>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $business->businessId() }} · {{ \App\Support\Countries::name($business->businessSetting?->country) ?? __('No country') }}</span>
                            </td>
                            <td>
                                <span class="text-sm text-[var(--text-primary)]">{{ $business->user?->name ?? '-' }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $business->user?->email }}</span>
                            </td>
                            <td>
                                <span class="text-sm">{{ $business->package?->name ?? '-' }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ __(\App\Services\Admin\BusinessService::SUBSCRIPTION_LABELS[\App\Services\Admin\BusinessService::subscriptionStatus($business)]) }}</span>
                            </td>
                            <td>@include('admin.businesses._status-badge', ['status' => $business->status])</td>
                            <td class="text-xs text-[var(--text-muted)] whitespace-nowrap">
                                @foreach($usage as $line)
                                    <span class="block {{ $line['reached'] ? 'text-[var(--danger)] font-medium' : '' }}">{{ $line['label'] }}</span>
                                @endforeach
                            </td>
                            <td class="text-sm text-[var(--text-muted)] whitespace-nowrap">{{ $business->created_at->format('M d, Y') }}</td>
                            <td class="text-sm text-[var(--text-muted)] whitespace-nowrap">{{ $business->last_activity_at ? \Illuminate\Support\Carbon::parse($business->last_activity_at)->diffForHumans() : __('None yet') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="building-2" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No businesses match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($businesses->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $businesses->links() }}
            </div>
        @endif
    </div>
@endsection
