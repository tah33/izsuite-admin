@extends('layouts.admin')
@section('title', __('Promotions'))

{{-- Doc 15 section 15.17: promotion codes, each a discount off a new subscription's first invoice, and how often each was taken. --}}
@php
    $role   = auth()->user()->role;
    $may    = fn (string $route) => (bool) $role?->hasPermission($route);
    $today  = now()->toDateString();
    $badges = [
        'active'    => ['badge-success', __('Active')],
        'paused'    => ['badge-inactive', __('Paused')],
        'scheduled' => ['badge-warning', __('Not started')],
        'ended'     => ['badge-inactive', __('Ended')],
        'used_up'   => ['badge-inactive', __('Used up')],
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Promotions') }}</h1>
            <p class="page-subtitle">{{ __('Codes a business types when it subscribes: a percent or an amount off its first invoice, before credits and tax.') }}</p>
        </div>
        @if($may('admin.promotions.create'))
            <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Add Promotion') }}</a>
        @endif
    </div>

    <form action="{{ route('admin.promotions.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name or code...') }}" class="form-input search-input w-full">
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.promotions.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    @error('promotion')
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $message }}</div>
    @enderror

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Promotion') }}</th>
                        <th>{{ __('Discount') }}</th>
                        <th>{{ __('For') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th class="text-end">{{ __('Taken') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promotion)
                        @php($status = $promotion->statusOn($today, (int) $promotion->uses))
                        <tr>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ $promotion->name }}</span>
                                <code class="block text-xs text-[var(--text-muted)]">{{ $promotion->code }}</code>
                            </td>
                            <td class="text-sm whitespace-nowrap">{{ $promotion->describe() }}</td>
                            <td class="text-xs text-[var(--text-secondary)]">
                                <span class="block">{{ $promotion->packages ? implode(', ', array_map(fn ($key) => $plans[$key] ?? $key, $promotion->packages)) : __('Every paid plan') }} · {{ $promotion->cycles ? implode(', ', $promotion->cycles) : __('monthly and yearly') }}</span>
                                <span class="block">{{ $promotion->countries ? implode(', ', $promotion->countries) : __('Every country') }}{{ $promotion->new_customers_only ? ' · '.__('new customers') : '' }}</span>
                            </td>
                            <td class="text-xs whitespace-nowrap">
                                @if($promotion->starts_on && $promotion->ends_on)
                                    {{ $promotion->starts_on->format('M d, Y') }} – {{ $promotion->ends_on->format('M d, Y') }}
                                @elseif($promotion->starts_on)
                                    {{ __('From :day', ['day' => $promotion->starts_on->format('M d, Y')]) }}
                                @elseif($promotion->ends_on)
                                    {{ __('Until :day', ['day' => $promotion->ends_on->format('M d, Y')]) }}
                                @else
                                    {{ __('No end') }}
                                @endif
                            </td>
                            <td class="text-sm text-end whitespace-nowrap">
                                {{ (int) $promotion->uses }}{{ $promotion->max_redemptions ? ' / '.$promotion->max_redemptions : '' }}
                                <span class="block text-xs text-[var(--text-muted)]">{{ trans_choice('once a business|:count times a business', $promotion->per_business) }}</span>
                            </td>
                            <td class="whitespace-nowrap"><span class="badge {{ $badges[$status][0] }}">{{ $badges[$status][1] }}</span></td>
                            <td class="text-end whitespace-nowrap">
                                @if($may('admin.promotions.edit'))
                                    <a href="{{ route('admin.promotions.edit', $promotion->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></a>
                                @endif
                                @if((int) $promotion->uses === 0 && $may('admin.promotions.destroy'))
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-promotion-{{ $promotion->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                    <x-confirm-modal
                                        id="delete-promotion-{{ $promotion->id }}"
                                        :title="__('Delete This Promotion?')"
                                        :message="__(':code was never taken. Deleted, it can no longer be used.', ['code' => $promotion->code])"
                                        :action="route('admin.promotions.destroy', $promotion->id)"
                                        method="DELETE"
                                        :confirm-text="__('Delete')"
                                        confirm-class="btn-danger"
                                        icon="trash-2"
                                    />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="ticket-percent" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No promotions yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($promotions->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $promotions->links() }}</div>
        @endif
    </div>
@endsection
