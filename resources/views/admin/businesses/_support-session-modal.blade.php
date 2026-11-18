{{-- Opening a support session in the business (Doc 15 section 15.22): for one of its open cases, with a reason, for an
     hour to four - read only, or with edit access ($edit), which also keeps how the customer agreed. Expects $openCases. --}}
@php($id = $edit ? 'support-session-edit-modal' : 'support-session-modal')
<div id="{{ $id }}" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ $edit ? __('Support Session With Edit Access') : __('Support Session') }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        @if($openCases->isEmpty())
            <div class="modal-body">
                <p class="text-sm text-[var(--text-secondary)]">{{ __('A session is for one of the business\'s open support cases, and it has none. Open a case first.') }}</p>
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Close') }}</button>
            </div>
        @else
            <form action="{{ route($edit ? 'admin.businesses.support-session-edit' : 'admin.businesses.support-session', $business->id) }}" method="POST">
                @csrf
                <div class="modal-body space-y-4">
                    <p class="text-sm text-[var(--text-secondary)]">
                        {{ $edit
                            ? __('Opens the business in the app as its owner, able to make changes - except to its billing, its ownership and its account - until the session ends. Payment details stay masked. The owner is told, and the business\'s Activity Log keeps everything the session changes as yours.')
                            : __('Opens the business in the app as its owner, to look, not to change anything, until the session ends. Payment details stay masked. The owner is told, and the business\'s Activity Log keeps the sensitive areas you open.') }}
                    </p>
                    <div class="form-group">
                        <label for="{{ $id }}-case" class="form-label">{{ __('Support case') }}</label>
                        <select id="{{ $id }}-case" name="ticket_id" class="form-select">
                            @foreach($openCases as $case)
                                <option value="{{ $case->id }}">#{{ $case->id }} {{ $case->subject }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="{{ $id }}-reason" class="form-label">{{ __('Reason (the business sees it)') }}</label>
                        <textarea id="{{ $id }}-reason" name="reason" rows="2" class="form-input" maxlength="500"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="{{ $id }}-hours" class="form-label">{{ __('For') }}</label>
                        <select id="{{ $id }}-hours" name="hours" class="form-select">
                            @foreach(range(1, \App\Services\Shared\SupportSessionService::MAX_HOURS) as $hours)
                                <option value="{{ $hours }}">{{ trans_choice(':count hour|:count hours', $hours) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($edit)
                        <div class="form-group">
                            <label for="{{ $id }}-agreement" class="form-label">{{ __('How the customer agreed to changes') }}</label>
                            <textarea id="{{ $id }}-agreement" name="customer_authorization" rows="2" class="form-input" maxlength="500" placeholder="{{ __('For example: the owner asked for it on the case, on 13 Nov') }}"></textarea>
                        </div>
                    @endif
                </div>
                <div class="modal-footer flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Open the Session') }}</button>
                </div>
            </form>
        @endif
    </div>
</div>
