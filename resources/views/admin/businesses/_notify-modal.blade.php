{{-- A message from izSuite to the business (Doc 15 section 15.5): in the app, and by e-mail to the owner. --}}
<div id="business-notify-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Send a Message') }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ route('admin.businesses.notify', $business->id) }}" method="POST">
            @csrf
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label for="notify_audience" class="form-label">{{ __('To') }}</label>
                    <select id="notify_audience" name="audience" class="form-select">
                        <option value="owner">{{ __('The owner') }}</option>
                        <option value="everyone">{{ __('Everyone in the business') }}</option>
                    </select>
                    <p class="text-xs text-[var(--text-muted)] mt-1">{{ __('In the app\'s bell, as Messages from izSuite; the owner also gets it by e-mail.') }}</p>
                </div>
                <div class="form-group">
                    <label for="notify_title" class="form-label">{{ __('Title') }}</label>
                    <input type="text" id="notify_title" name="title" class="form-input" maxlength="150">
                </div>
                <div class="form-group">
                    <label for="notify_message" class="form-label">{{ __('Message') }}</label>
                    <textarea id="notify_message" name="message" rows="4" class="form-input" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Send') }}</button>
            </div>
        </form>
    </div>
</div>
