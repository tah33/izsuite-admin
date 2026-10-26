{{-- Settings > Mail: send a test email with the saved settings --}}
<form action="{{ route('admin.settings.test-mail') }}" method="POST" novalidate>
    @csrf

    <div class="settings-panel card p-6 mb-4 {{ $group !== $activeTab ? 'hidden' : '' }}" data-tab="{{ $group }}">
        <h2 class="text-lg font-semibold mb-1 text-[var(--text-primary)]">{{ __('Send a test email') }}</h2>
        <p class="text-sm text-[var(--text-muted)] mb-4">
            {{ __('Save your settings first, then send a test message to confirm the credentials work.') }}
        </p>

        <div class="flex items-center gap-2 mb-4 text-xs">
            <span class="text-[var(--text-muted)]">{{ __('Active mailer') }}:</span>
            <span class="px-2 py-0.5 rounded-full font-mono font-medium {{ config('mail.default') === 'smtp' ? 'bg-[var(--success-light)] text-[var(--primary-dark)]' : 'bg-[var(--danger-light)] text-[var(--danger)]' }}">
                {{ config('mail.default') }}
            </span>
            @if(config('mail.default') !== 'smtp')
                <span class="text-[var(--text-muted)]">{{ __('- turn on "Use custom SMTP" above to send through your own server.') }}</span>
            @endif
        </div>

        <div class="form-group">
            <label for="test_email" class="form-label">{{ __('Send to') }} <span class="text-[var(--danger)]">*</span></label>
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="email"
                    name="test_email"
                    id="test_email"
                    class="form-input flex-1 {{ $errors->first('test_email') ? 'form-input-error' : '' }}"
                    value="{{ old('test_email', auth()->user()?->email) }}"
                    placeholder="you@example.com"
                    required
                >
                <button type="submit" class="btn btn-primary whitespace-nowrap">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    {{ __('Send Test Email') }}
                </button>
            </div>

            @if($errors->first('test_email'))
                <p class="form-error" style="color: #ef4444; display: flex; align-items: center; gap: 4px;">
                    <i data-lucide="alert-circle" class="w-3.5 h-3.5" style="color: #ef4444; margin: 0;"></i>
                    <span style="font-size: 0.7rem; font-weight: 500;">{{ $errors->first('test_email') }}</span>
                </p>
            @endif
        </div>
    </div>
</form>
