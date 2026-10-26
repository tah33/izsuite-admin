<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Login Demo — {{ config('brand.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
@include('social-login-demo._styles-page')

@include('social-login-demo._styles-status')
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="logo">
            <img src="{{ asset(config('brand.assets.wordmark')) }}"
                 alt="{{ config('brand.name') }}" style="height:34px;width:auto;display:inline-block;">
        </div>
        <p class="subtitle">Social Login Demo — Obtain tokens &amp; test the API</p>

        @if(empty(config('services.google.client_id')) && empty(config('services.linkedin.client_id')))
            <div class="config-warning">
                ⚠️ Both <code>GOOGLE_CLIENT_ID</code> and <code>LINKEDIN_CLIENT_ID</code> are empty in your <code>.env</code>.
                Set at least one to test social login.
            </div>
        @elseif(empty(config('services.google.client_id')))
            <div class="config-warning">
                ⚠️ <code>GOOGLE_CLIENT_ID</code> is not set in <code>.env</code>. Google sign-in is disabled.
            </div>
        @elseif(empty(config('services.linkedin.client_id')))
            <div class="config-warning">
                ⚠️ <code>LINKEDIN_CLIENT_ID</code> is not set in <code>.env</code>. LinkedIn sign-in is disabled.
            </div>
        @endif

        {{-- Google Sign-In --}}
        <button
            id="btn-google"
            class="social-btn google"
            onclick="handleGoogleLogin()"
            @if(empty(config('services.google.client_id'))) disabled title="Set GOOGLE_CLIENT_ID in .env" @endif
        >
            <svg viewBox="0 0 24 24">
                <path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.97h3.86c2.26-2.09 3.56-5.17 3.56-8.79z"/>
                <path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-2.97c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.07C3.515 21.27 7.565 24 12.255 24z"/>
                <path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.64h-3.98a11.86 11.86 0 000 10.72l3.98-3.07z"/>
                <path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.69 0-8.74 2.73-10.71 6.64l3.98 3.07c.95-2.85 3.6-4.96 6.73-4.96z"/>
            </svg>
            Sign in with Google
        </button>

        {{-- LinkedIn Sign-In --}}
        <button
            id="btn-linkedin"
            class="social-btn linkedin"
            onclick="handleLinkedInLogin()"
            @if(empty(config('services.linkedin.client_id'))) disabled title="Set LINKEDIN_CLIENT_ID in .env" @endif
        >
            <svg viewBox="0 0 24 24" fill="#0a66c2">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
            </svg>
            Sign in with LinkedIn
        </button>

        {{-- Status --}}
        <div id="status" class="status"></div>
    </div>

    <p class="footer-note">
        This page is for <strong>development testing only</strong>.<br>
        Tokens are sent to <a href="#">POST /api/v1/auth/social</a>
    </p>
</div>

{{-- Google Identity Services Library --}}
@if(config('services.google.client_id'))
<script src="https://accounts.google.com/gsi/client" async defer></script>
@endif

<script>
@include('social-login-demo._script-sign-in')

@include('social-login-demo._script-api')
</script>

</body>
</html>
