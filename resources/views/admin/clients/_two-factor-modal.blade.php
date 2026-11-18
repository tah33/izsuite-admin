{{-- Resetting the account's two-factor sign-in (Doc 15 section 15.7): once support has checked who is asking, and as a critical action (15.25). --}}
<div id="client-two-factor-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Reset Two-Factor Sign-in') }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ route('admin.clients.two-factor', $client->id) }}" method="POST">
            @csrf
            <div class="modal-body space-y-4">
                <p class="text-sm text-[var(--text-secondary)]">{{ __('For someone who has lost both their phone and their recovery codes. Do it only once you have checked that the person asking owns the account.') }}</p>
                <div class="form-group">
                    <label for="client-identity-check" class="form-label">{{ __('How you checked who is asking') }}</label>
                    <textarea id="client-identity-check" name="identity_check" rows="2" class="form-input" maxlength="500" placeholder="{{ __('For example: wrote from the account\'s email address, and named its business\'s last invoice') }}"></textarea>
                </div>
                @include('admin.partials.critical-confirm', [
                    'impact'  => __('Two-factor sign-in turns off for :name, and every session of theirs ends. They are told by e-mail.', ['name' => $client->name]),
                    'id'      => 'client-two-factor',
                    'compact' => true,
                ])
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('Reset Two-Factor') }}</button>
            </div>
        </form>
    </div>
</div>
