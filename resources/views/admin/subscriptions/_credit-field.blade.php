{{-- How much credit is given or taken back (Doc 15 Phase C2b), in the plan's currency. Inside admin.partials.reason-modal: $id is the dialog's, $currency the plan's, $most the most it can be. --}}
<div class="form-group w-56">
    <label for="{{ $id }}-amount" class="form-label">{{ $most ? __('Amount (:currency), up to :most', ['currency' => $currency, 'most' => $most]) : __('Amount (:currency)', ['currency' => $currency]) }}</label>
    <input type="number" id="{{ $id }}-amount" name="amount" min="0.01" step="0.01" @if($most) max="{{ $most }}" value="{{ $most }}" @endif class="form-input" required>
</div>
