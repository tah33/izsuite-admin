@extends('layouts.guest')
@section('title', __('Set Up Two-Factor Sign-In'))

{{-- Every admin signs in with an authenticator app as well (Doc 15 section 15.2). Until it is set up, the panel shows only this. --}}
@section('content')
    <h2 class="text-xl font-bold mb-1 text-[var(--text-primary)]">{{ __('Set up two-factor sign-in') }}</h2>
    <p class="text-sm mb-5 text-[var(--text-secondary)]">
        {{ __('Every admin signs in with a code from an authenticator app as well as the password. Set it up once to open the admin panel.') }}
    </p>

    <ol class="space-y-5 text-sm text-[var(--text-secondary)]">
        <li>
            <p class="font-medium text-[var(--text-primary)] mb-2">1. {{ __('Scan this code with your authenticator app') }}</p>
            <p class="mb-3">{{ __('Google Authenticator, Microsoft Authenticator, Authy or any app for one-time codes.') }}</p>
            <div class="flex justify-center p-3 rounded-lg border border-[var(--card-border)] bg-white">
                <canvas id="two-factor-qr" width="192" height="192" aria-label="{{ __('QR code for your authenticator app') }}"></canvas>
            </div>
            <p class="mt-3">{{ __('Cannot scan it? Enter this key in the app instead:') }}</p>
            <p class="mt-1 font-mono text-[13px] tracking-wider text-[var(--text-primary)] break-all select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</p>
        </li>

        <li>
            <p class="font-medium text-[var(--text-primary)] mb-2">2. {{ __('Enter the 6-digit code the app shows') }}</p>
            <form method="POST" action="{{ route('admin.two-factor.confirm') }}">
                @csrf
                <div class="mb-4">
                    <label for="code" class="form-label">{{ __('Code') }}</label>
                    <input type="text" id="code" name="code" class="form-input tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="10" placeholder="123456" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('Turn On and Continue') }}</button>
            </form>
        </li>
    </ol>
@endsection

@section('below-card')
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-[var(--text-secondary)] hover:underline">{{ __('Sign out') }}</button>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/qrcode/qrcode.min.js') }}"></script>
    <script>
        QRCode.toCanvas(document.getElementById('two-factor-qr'), @json($uri), { width: 192, margin: 1 }, function (error) {
            if (error && window.console) {
                console.error('[admin] QR code not drawn:', error);
            }
        });
    </script>
@endpush
