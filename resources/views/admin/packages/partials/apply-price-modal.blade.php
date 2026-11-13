{{--
    A plan's price applied to its subscribers (Doc 15 Phase C3a): those renewing on it in this currency and cycle at
    another price move to the one in force on the day chosen - at least 30 days ahead - from their first renewal on or
    after it, and each is e-mailed now. A critical action: the password and a reason. Expects $package and $price.
--}}
<div id="apply-price-{{ $price->id }}" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Apply the Price to Subscribers') }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ route('admin.packages.price-apply', $package->id) }}" method="POST">
            @csrf
            <input type="hidden" name="currency" value="{{ $price->currency }}">
            <input type="hidden" name="cycle" value="{{ $price->cycle }}">
            <div class="modal-body space-y-4">
                <p class="text-sm text-[var(--text-secondary)]">{{ __('Subscribers paying another :cycle price in :currency move to the price in force on the day you choose, from their first renewal on or after it.', ['cycle' => $price->cycle, 'currency' => $price->currency]) }}</p>
                <div class="form-group w-56">
                    <label for="apply-{{ $price->id }}-from" class="form-label">{{ __('From') }}</label>
                    <input type="date" id="apply-{{ $price->id }}-from" name="from" min="{{ now()->addDays(30)->toDateString() }}" value="{{ now()->addDays(30)->toDateString() }}" class="form-input" required>
                </div>
                @include('admin.partials.critical-confirm', [
                    'id'      => 'apply_'.$price->id,
                    'compact' => true,
                    'impact'  => __('Every one of them is e-mailed now with the new price and its day. Invoices already issued keep their amounts.'),
                ])
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Apply') }}</button>
            </div>
        </form>
    </div>
</div>
