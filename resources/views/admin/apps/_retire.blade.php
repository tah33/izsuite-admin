{{--
    Retiring an app (Doc 15 section 15.10 - Phase E2): it leaves the Apps Center and the website, and goes off for every
    business with it on - their records kept, as turning it off keeps them. A critical action: the password and a
    reason. Expects $app.
--}}
<div class="card mt-6 border-[var(--danger)]">
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
            <h2 class="section-title mb-1">{{ __('Retire This App') }}</h2>
            <p class="text-sm text-[var(--text-muted)]">{{ __('It goes off for every business with it on, and leaves the Apps Center. Their records stay, as when a business turns it off.') }}</p>
        </div>
        <button type="button" class="btn bg-[var(--danger-light)] text-[var(--danger)]" data-modal-open="retire-app">
            <i data-lucide="archive" class="w-4 h-4"></i>{{ __('Retire') }}
        </button>
    </div>
</div>

<div id="retire-app" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Retire :app?', ['app' => $app->name]) }}</h3>
            <button type="button" data-modal-close class="p-1 rounded-lg hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <i data-lucide="x" class="w-5 h-5 text-[var(--text-secondary)]"></i>
            </button>
        </div>

        <form action="{{ route('admin.apps.retire', $app->id) }}" method="POST">
            @csrf
            <div class="modal-body space-y-4">
                @include('admin.partials.critical-confirm', [
                    'id'      => 'retire',
                    'compact' => true,
                    'impact'  => __(':app goes off now for every business with it on.', ['app' => $app->name]),
                ])
            </div>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('Retire') }}</button>
            </div>
        </form>
    </div>
</div>
