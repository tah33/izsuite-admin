{{--
    A plan's prices (Doc 15 sections 15.8 and 15.9 - Phase C3a): each for a currency and cycle from its
    start day, the one before ending the day before - history kept. A price not started yet can be removed.
    Subscribers keep the price they pay until izSuite applies a new one to them. Expects $package, $prices,
    $paid, $currencies and $may.
--}}
@php
    $today  = now()->toDateString();
    // A price not started yet is labelled with its day, in the row.
    $badges = ['scheduled' => ['badge-warning', null], 'current' => ['badge-success', __('In force')], 'ended' => ['badge-inactive', __('Ended')]];
@endphp
<div class="card overflow-hidden mt-6" id="prices">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Prices') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ __('A business pays in its own currency when the plan has a price in it, else in :main. Subscribers keep the price they pay until you apply a new one to them, from a day at least 30 days ahead.', ['main' => $package->price_currency]) }}</p>
    </div>

    @if($prices->isEmpty())
        <p class="px-6 pb-4 text-sm text-[var(--text-muted)]">{{ __('No price yet: the plan shows "Price to be announced".') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Currency') }}</th>
                        <th>{{ __('Cycle') }}</th>
                        <th class="text-end">{{ __('Price') }}</th>
                        <th>{{ __('In Force') }}</th>
                        <th>{{ __('Subscribers') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prices as $price)
                        @php
                            $status = $price->statusOn($today);
                            $mine   = $paid->where('currency', $price->currency)->where('cycle', $price->cycle);
                            $is     = fn ($amount) => $amount !== null && bccomp((string) $amount, (string) $price->amount, 2) === 0;
                            $same   = (int) $mine->filter(fn ($row) => $is($row->price))->sum('subscriptions');
                            // Applied already: they move to it from their day.
                            $moving = (int) $mine->filter(fn ($row) => ! $is($row->price) && $is($row->next_price))->sum('subscriptions');
                            $other  = (int) $mine->sum('subscriptions') - $same - $moving;
                        @endphp
                        <tr>
                            <td class="text-sm font-medium">{{ $price->currency }}</td>
                            <td class="text-sm">{{ __(ucfirst($price->cycle)) }}</td>
                            <td class="text-sm text-end whitespace-nowrap font-semibold">{{ number_format((float) $price->amount, 2) }}</td>
                            <td class="text-sm whitespace-nowrap">
                                <span class="badge {{ $badges[$status][0] }}">{{ $status === 'scheduled' ? __('Starts :date', ['date' => $price->starts_on->format('M d, Y')]) : $badges[$status][1] }}</span>
                                <span class="block text-xs text-[var(--text-muted)] mt-1">{{ $price->starts_on->format('M d, Y') }} – {{ $price->ends_on?->format('M d, Y') ?? __('on') }}{{ $price->creator ? ' · '.$price->creator->name : '' }}</span>
                            </td>
                            <td class="text-sm">
                                @if($status === 'current')
                                    {{ trans_choice(':count pays it|:count pay it', $same) }}
                                    @if($moving > 0)
                                        <span class="block text-xs text-[var(--text-muted)]">{{ trans_choice(':count moves to it|:count move to it', $moving) }}</span>
                                    @endif
                                    @if($other > 0)
                                        <span class="block text-xs text-[var(--warning)]">{{ trans_choice(':count pays another price|:count pay another price', $other) }}</span>
                                    @endif
                                @else
                                    <span class="text-[var(--text-muted)]">—</span>
                                @endif
                            </td>
                            <td class="text-end whitespace-nowrap">
                                @if($status === 'current' && $other > 0 && $may('admin.packages.price-apply'))
                                    <button type="button" class="btn btn-xs btn-secondary" data-modal-open="apply-price-{{ $price->id }}">{{ __('Apply to Subscribers') }}</button>
                                @endif
                                @if($status === 'scheduled' && $may('admin.packages.price-remove'))
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Remove') }}" onclick="openConfirmModal('remove-price-{{ $price->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                    <x-confirm-modal
                                        id="remove-price-{{ $price->id }}"
                                        :title="__('Remove This Price?')"
                                        :message="__('The :cycle price of :currency :amount will not start on :date; the one before it carries on.', ['cycle' => $price->cycle, 'currency' => $price->currency, 'amount' => $price->amount, 'date' => $price->starts_on->format('M d, Y')])"
                                        :action="route('admin.packages.price-remove', [$package->id, $price->id])"
                                        method="DELETE"
                                        :confirm-text="__('Remove')"
                                        confirm-class="btn-danger"
                                        icon="trash-2"
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($may('admin.packages.price'))
        <form action="{{ route('admin.packages.price', $package->id) }}" method="POST" class="p-6 pt-4 border-t border-[var(--card-border)] grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            @csrf
            <div>
                <label for="price-currency" class="form-label text-xs">{{ __('Currency') }}</label>
                <select id="price-currency" name="currency" class="form-select">
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->code }}" @selected($currency->code === $package->price_currency)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="price-cycle" class="form-label text-xs">{{ __('Cycle') }}</label>
                <select id="price-cycle" name="cycle" class="form-select">
                    <option value="monthly">{{ __('Monthly') }}</option>
                    <option value="yearly">{{ __('Yearly') }}</option>
                </select>
            </div>
            <div>
                <label for="price-amount" class="form-label text-xs">{{ __('Price') }}</label>
                <input type="number" id="price-amount" name="amount" min="0" step="0.01" class="form-input" required>
            </div>
            <div>
                <label for="price-starts" class="form-label text-xs">{{ __('From') }}</label>
                <input type="date" id="price-starts" name="starts_on" min="{{ $today }}" value="{{ $today }}" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Add Price') }}</button>
        </form>
    @endif
</div>

@foreach($prices as $price)
    @if($price->statusOn($today) === 'current' && $may('admin.packages.price-apply'))
        @include('admin.packages.partials.apply-price-modal')
    @endif
@endforeach
