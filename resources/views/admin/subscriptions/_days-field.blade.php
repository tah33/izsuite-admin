{{-- By how many days a trial or a grace period is extended. Inside admin.partials.reason-modal: $id is the dialog's. --}}
<div class="form-group w-40">
    <label for="{{ $id }}-days" class="form-label">{{ __('Days to add') }}</label>
    <input type="number" id="{{ $id }}-days" name="days" min="1" max="60" value="7" class="form-input">
</div>
