{{-- What can be done with a privacy request now (Doc 15 section 15.24): check the asker, then carry it out or reject it. Expects $privacy, $Privacy, $businesses, $exportDays and $may. --}}
@php $hint = 'text-xs mt-1 text-[var(--text-muted)]'; @endphp

@if($privacy->status === $Privacy::RECEIVED && $may('admin.privacy-requests.verify'))
    <form action="{{ route('admin.privacy-requests.verify', $privacy->id) }}" method="POST" class="card">
        @csrf
        <h2 class="section-title mb-2">{{ __('Check Who Is Asking') }}</h2>
        <label class="form-label" for="identity_check">{{ __('How You Checked') }}</label>
        <textarea id="identity_check" name="identity_check" rows="3" class="form-input" maxlength="1000" required placeholder="{{ __('e.g. Replied from the account\'s own e-mail; confirmed the last invoice number.') }}"></textarea>
        <button type="submit" class="btn btn-primary mt-3"><i data-lucide="user-check" class="w-4 h-4"></i>{{ __('They Are Who They Say') }}</button>
    </form>
@endif

@if($privacy->status === $Privacy::VERIFIED)
    @if($privacy->type === $Privacy::EXPORT && $may('admin.privacy-requests.export'))
        <form action="{{ route('admin.privacy-requests.export', $privacy->id) }}" method="POST" class="card">
            @csrf
            <h2 class="section-title mb-2">{{ __('Export the Data') }}</h2>
            <p class="text-sm mb-3 text-[var(--text-secondary)]">{{ __('Every record of :business, as CSV files in a ZIP. Its link goes to :email and works for :days days; then the file is deleted.', ['business' => $privacy->workspace?->name, 'email' => $privacy->requester_email, 'days' => $exportDays]) }}</p>
            <button type="submit" class="btn btn-primary"><i data-lucide="file-archive" class="w-4 h-4"></i>{{ __('Prepare the Export') }}</button>
        </form>
    @endif

    @if($privacy->type === $Privacy::CLOSURE && $may('admin.privacy-requests.close'))
        <div class="card">
            <h2 class="section-title mb-2">{{ __('Close the Account') }}</h2>
            <p class="text-sm text-[var(--text-secondary)]">{{ $businesses->isEmpty() ? __('It owns no business.') : __('It owns :names: they close with it.', ['names' => $businesses->pluck('name')->implode(', ')]) }} {{ __('Every record is kept.') }}</p>
            <button type="button" class="btn btn-danger mt-3" onclick="openConfirmModal('close-account')"><i data-lucide="user-x" class="w-4 h-4"></i>{{ __('Close the Account') }}</button>
            <x-confirm-modal
                id="close-account"
                :title="__('Close This Account?')"
                :message="__('Nobody signs in to :email again, and nobody opens the businesses it owns. Their records are kept.', ['email' => $privacy->user?->email])"
                :action="route('admin.privacy-requests.close', $privacy->id)"
                method="POST"
                :confirm-text="__('Close It')"
                confirm-class="btn-danger"
                icon="user-x"
            />
        </div>
    @endif

    @if($privacy->type === $Privacy::CORRECTION && $may('admin.privacy-requests.complete'))
        <form action="{{ route('admin.privacy-requests.complete', $privacy->id) }}" method="POST" class="card">
            @csrf
            <h2 class="section-title mb-2">{{ __('Complete the Correction') }}</h2>
            <label class="form-label" for="outcome">{{ __('What Was Corrected') }}</label>
            <textarea id="outcome" name="outcome" rows="3" class="form-input" maxlength="2000" required></textarea>
            <label class="flex items-center gap-2 mt-2 text-sm"><input type="checkbox" name="notify" value="1" class="form-checkbox" checked> {{ __('E-mail it to the person asking') }}</label>
            <button type="submit" class="btn btn-primary mt-3"><i data-lucide="check" class="w-4 h-4"></i>{{ __('Completed') }}</button>
        </form>
    @endif
@endif

@if($privacy->status === $Privacy::IN_PROGRESS)
    <div class="card">
        <h2 class="section-title mb-2">{{ __('Export Being Prepared') }}</h2>
        <p class="text-sm text-[var(--text-secondary)]">{{ __('Its link goes to :email as soon as it is ready. Reload to see.', ['email' => $privacy->requester_email]) }}</p>
    </div>
@endif

@if($privacy->exportAvailable() && $may('admin.privacy-requests.download'))
    <div class="card">
        <h2 class="section-title mb-2">{{ __('The Export') }}</h2>
        <a href="{{ route('admin.privacy-requests.download', $privacy->id) }}" class="btn btn-secondary"><i data-lucide="download" class="w-4 h-4"></i>{{ __('Download') }}</a>
        <p class="{{ $hint }}">{{ __('Every download is in the audit log.') }}</p>
    </div>
@endif

@if(in_array($privacy->status, [$Privacy::RECEIVED, $Privacy::VERIFIED], true) && $may('admin.privacy-requests.reject'))
    <form action="{{ route('admin.privacy-requests.reject', $privacy->id) }}" method="POST" class="card">
        @csrf
        <h2 class="section-title mb-2">{{ __('Reject It') }}</h2>
        <label class="form-label" for="reject_outcome">{{ __('Why') }}</label>
        <textarea id="reject_outcome" name="outcome" rows="3" class="form-input" maxlength="2000" required placeholder="{{ __('e.g. We could not check that you own the account.') }}"></textarea>
        <label class="flex items-center gap-2 mt-2 text-sm"><input type="checkbox" name="notify" value="1" class="form-checkbox" checked> {{ __('E-mail it to the person asking') }}</label>
        <button type="submit" class="btn btn-secondary mt-3"><i data-lucide="x-circle" class="w-4 h-4"></i>{{ __('Reject') }}</button>
    </form>
@endif
