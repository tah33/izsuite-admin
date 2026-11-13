{{-- Changing a business's status (Doc 15 section 15.6): every change keeps its reason, and the owner is told unless unticked. --}}
<div id="business-status-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Change Status') }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ route('admin.businesses.status', $business->id) }}" method="POST">
            @csrf
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label for="business_status" class="form-label">{{ __('New status') }}</label>
                    <select id="business_status" name="status" class="form-select">
                        @foreach(\App\Support\BusinessStatus::SETTABLE as $value)
                            <option value="{{ $value }}" @selected(($business->status ?: 'active') === $value)>{{ __(\App\Support\BusinessStatus::label($value)) }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-[var(--text-muted)] mt-1">{{ __('Restricted and Under Review: members read but change nothing. Suspended: nobody opens it. Closure Scheduled: it works until the day, then closes.') }}</p>
                </div>
                <div class="form-group" data-closes-on>
                    <label for="business_closes_on" class="form-label">{{ __('Closes on') }}</label>
                    <input type="date" id="business_closes_on" name="closes_on" class="form-input" min="{{ today()->toDateString() }}">
                </div>
                <div class="form-group">
                    <label for="business_reason" class="form-label">{{ __('Reason (the owner sees it)') }}</label>
                    <textarea id="business_reason" name="reason" rows="2" class="form-input" maxlength="1000"></textarea>
                </div>
                <div class="form-group">
                    <label for="business_reactivation" class="form-label">{{ __('What reactivates it (optional, the owner sees it)') }}</label>
                    <input type="text" id="business_reactivation" name="reactivation" class="form-input" maxlength="1000">
                </div>
                <div class="form-group">
                    <label for="business_notes" class="form-label">{{ __('Internal notes (admins only)') }}</label>
                    <textarea id="business_notes" name="notes" rows="2" class="form-input" maxlength="2000"></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="notify" value="0">
                    <input type="checkbox" name="notify" value="1" class="form-checkbox" checked>
                    {{ __('Tell the owner, in the app and by e-mail') }}
                </label>
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save Status') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        // The closing day only matters to a scheduled closure.
        $(function () {
            var $status = $('#business_status');
            var toggle = function () { $('[data-closes-on]').toggle($status.val() === 'closure_scheduled'); };
            $status.on('change', toggle);
            toggle();
        });
    </script>
@endpush
