@extends('layouts.guest')
@section('title', 'Two-Factor Sign-In')

{{-- The admin sign-in's code step (Doc 15 section 15.2): the authenticator app's code, or a recovery code. --}}
@section('content')
    <h2 class="text-xl font-bold mb-1 text-[var(--text-primary)]">Two-factor sign-in</h2>
    <p class="text-sm mb-6 text-[var(--text-secondary)]" data-code-hint>Enter the 6-digit code your authenticator app shows for izSuite Admin.</p>
    <p class="text-sm mb-6 text-[var(--text-secondary)] hidden" data-recovery-hint>Enter one of the recovery codes you saved when you set up two-factor sign-in. Each works once.</p>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg text-sm bg-[var(--danger-light)]">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.two-factor') }}">
        @csrf

        <div class="mb-6" data-code-field>
            <label for="code" class="form-label">Code</label>
            <input type="text" id="code" name="code" class="form-input tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="10" placeholder="123456" autofocus>
        </div>

        <div class="mb-6 hidden" data-recovery-field>
            <label for="recovery_code" class="form-label">Recovery code</label>
            <input type="text" id="recovery_code" name="recovery_code" class="form-input" autocomplete="off" maxlength="20" placeholder="ABCDE-FGHJK">
        </div>

        <button type="submit" class="btn btn-primary w-full">Sign In</button>
    </form>

    <div class="mt-4 flex items-center justify-between text-sm">
        <button type="button" class="text-[var(--primary)] hover:underline" data-toggle-recovery>Use a recovery code</button>
        <a href="{{ route('login') }}" class="text-[var(--text-secondary)] hover:underline">Back to sign in</a>
    </div>
@endsection

@push('scripts')
    <script>
        // One field at a time: the app's code, or a recovery code - the other is left empty.
        (function () {
            var toggle = document.querySelector('[data-toggle-recovery]');
            var usingRecovery = false;

            toggle.addEventListener('click', function () {
                usingRecovery = ! usingRecovery;

                document.querySelector('[data-code-field]').classList.toggle('hidden', usingRecovery);
                document.querySelector('[data-code-hint]').classList.toggle('hidden', usingRecovery);
                document.querySelector('[data-recovery-field]').classList.toggle('hidden', ! usingRecovery);
                document.querySelector('[data-recovery-hint]').classList.toggle('hidden', ! usingRecovery);
                document.getElementById(usingRecovery ? 'code' : 'recovery_code').value = '';
                document.getElementById(usingRecovery ? 'recovery_code' : 'code').focus();
                toggle.textContent = usingRecovery ? 'Use the app\'s code' : 'Use a recovery code';
            });
        })();
    </script>
@endpush
