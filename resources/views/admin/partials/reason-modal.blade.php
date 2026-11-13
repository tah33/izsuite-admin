{{-- An admin action and its reason, which the audit log keeps. Expects $id, $title, $text, $route and $button; $danger for
     a red button; $fields, a view of more fields to show above the reason. --}}
<div id="{{ $id }}" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ $title }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ $route }}" method="POST">
            @csrf
            <div class="modal-body space-y-4">
                <p class="text-sm text-[var(--text-secondary)]">{{ $text }}</p>
                @if(! empty($fields))
                    @include($fields)
                @endif
                <div class="form-group">
                    <label for="{{ $id }}-reason" class="form-label">{{ __('Reason') }}</label>
                    <textarea id="{{ $id }}-reason" name="reason" rows="3" class="form-input" maxlength="500" placeholder="{{ __('Why, for the audit log') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn {{ ($danger ?? false) ? 'btn-danger' : 'btn-primary' }}">{{ $button }}</button>
            </div>
        </form>
    </div>
</div>
