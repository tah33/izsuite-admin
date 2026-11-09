@extends('layouts.admin')
@section('title', __('Subscription Taxes'))

{{-- The tax izSuite adds to businesses' subscription invoices, by country
     (Doc 15 section 16 - Doc 13 Phase D1). Each country's latest rule that
     has started is in force; an invoice keeps the tax it was issued with. --}}
@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.subscription-taxes.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Country...') }}" class="form-input search-input w-full">
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
            <a href="{{ route('admin.subscription-taxes.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
        </form>
        <a href="{{ route('admin.subscription-taxes.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('New Tax') }}</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Country') }}</th>
                        <th>{{ __('Tax') }}</th>
                        <th>{{ __('Rate') }}</th>
                        <th>{{ __('From') }}</th>
                        <th>{{ __('With a tax ID') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxes as $tax)
                        @php
                            $status = $tax->starts_on->isFuture() ? ['badge-warning', __('Upcoming')] : (in_array($tax->id, $inForce, true) ? ['badge-success', __('In force')] : ['badge-inactive', __('Replaced')]);
                        @endphp
                        <tr>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ \App\Support\Countries::name($tax->country) ?? $tax->country }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $tax->country }}</span>
                            </td>
                            <td class="text-sm">{{ $tax->name }}</td>
                            <td class="text-sm whitespace-nowrap font-semibold">{{ $tax->percent() }}%</td>
                            <td class="text-sm whitespace-nowrap">{{ $tax->starts_on->format('M d, Y') }}</td>
                            <td class="text-sm">
                                @if($tax->reverse_charge)
                                    {{ __('Reverse charge') }}
                                    <span class="block text-xs text-[var(--text-muted)]">{{ $tax->reverse_charge_note ?: \App\Models\Billing\SubscriptionTax::REVERSE_CHARGE_NOTE }}</span>
                                @else
                                    <span class="text-[var(--text-muted)]">{{ __('Charged as usual') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap"><span class="badge {{ $status[0] }}">{{ $status[1] }}</span></td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.subscription-taxes.edit', $tax->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></a>
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-tax-{{ $tax->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                    <x-confirm-modal
                                        id="delete-tax-{{ $tax->id }}"
                                        :title="__('Delete Subscription Tax?')"
                                        :message="__('Invoices issued from now on no longer carry :tax. Invoices already issued keep the tax they had.', ['tax' => $tax->name.' '.$tax->percent().'%'])"
                                        :action="route('admin.subscription-taxes.destroy', $tax->id)"
                                        method="DELETE"
                                        :confirm-text="__('Delete')"
                                        confirm-class="btn-danger"
                                        icon="trash-2"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="percent" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ request('search') ? __('No subscription taxes for that country.') : __('No subscription taxes yet: invoices carry no tax.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($taxes->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $taxes->links() }}
            </div>
        @endif
    </div>
@endsection
