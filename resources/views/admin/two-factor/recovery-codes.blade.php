@extends('layouts.guest')
@section('title', __('Recovery Codes'))

{{-- Shown this once (Doc 15 section 15.2): only their hashes are kept. --}}
@section('content')
    <h2 class="text-xl font-bold mb-1 text-[var(--text-primary)]">{{ __('Save your recovery codes') }}</h2>
    <p class="text-sm mb-5 text-[var(--text-secondary)]">
        {{ __('If your phone is not to hand, each of these signs you in once in place of the app\'s code. Keep them somewhere safe: they are shown only now.') }}
    </p>

    <ul id="recovery-codes" class="grid grid-cols-2 gap-2 p-4 mb-4 rounded-lg border border-[var(--card-border)] bg-[var(--content-bg)] font-mono text-sm text-[var(--text-primary)]">
        @foreach($codes as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>

    <div class="flex gap-3">
        <button type="button" class="btn btn-secondary flex-1" data-copy-codes>
            <i data-lucide="copy"></i>{{ __('Copy') }}
        </button>
        <a href="{{ route('admin.overview') }}" class="btn btn-primary flex-1">{{ __('I Have Saved Them') }}</a>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelector('[data-copy-codes]').addEventListener('click', function () {
            var codes = Array.from(document.querySelectorAll('#recovery-codes li')).map(function (li) { return li.textContent.trim(); }).join('\n');

            navigator.clipboard.writeText(codes).then(function () {
                window.AdminToast && window.AdminToast.success(@json(__('Recovery codes copied.')));
            });
        });
    </script>
@endpush
