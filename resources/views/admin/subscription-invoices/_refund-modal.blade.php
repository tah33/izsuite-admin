{{--
    Refunding one payment (Doc 15 sections 14 and 15.25): how much - up to what is left - and,
    for a transfer, the bank's reference of izSuite's transfer back; then the admin's password
    and the reason, as for every critical action. Expects $payment and $left.
--}}
<div id="refund-payment-{{ $payment->id }}" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Refund a Payment') }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ route('admin.payments.refund', $payment->id) }}" method="POST">
            @csrf
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label for="refund-{{ $payment->id }}-amount" class="form-label">{{ __('Amount (:currency), up to :left', ['currency' => $payment->currency, 'left' => $left]) }}</label>
                    <input type="number" id="refund-{{ $payment->id }}-amount" name="amount" value="{{ $left }}" min="0.01" max="{{ $left }}" step="0.01" class="form-input" required>
                </div>
                @if($payment->isTransfer())
                    <div class="form-group">
                        <label for="refund-{{ $payment->id }}-reference" class="form-label">{{ __('Your bank\'s reference for the transfer back (optional)') }}</label>
                        <input type="text" id="refund-{{ $payment->id }}-reference" name="transfer_reference" maxlength="100" class="form-input">
                    </div>
                @endif
                @include('admin.partials.critical-confirm', [
                    'id'      => 'refund_'.$payment->id,
                    'compact' => true,
                    'impact'  => $payment->isTransfer()
                        ? __('This records that izSuite has sent the money back by bank transfer, so send it from izSuite\'s bank first. The business is told. The invoice and the plan stay as they are.')
                        : __('The card provider gives the money back to :card. It counts once the provider confirms it, and the business is told then. The invoice and the plan stay as they are.', ['card' => $payment->method()]),
                ])
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger" data-loading-label="{{ __('Refunding...') }}">{{ __('Refund') }}</button>
            </div>
        </form>
    </div>
</div>
