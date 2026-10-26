    const API_BASE = '{{ rtrim(config('app.url'), '/') }}/api/v1';
    const GOOGLE_CLIENT_ID = '{{ config('services.google.client_id') }}';
    const LINKEDIN_CLIENT_ID = '{{ config('services.linkedin.client_id') }}';

    // ─── Google Sign-In ────────────────────────────────────────
    function handleGoogleLogin() {
        if (!GOOGLE_CLIENT_ID) return;

        const btn = document.getElementById('btn-google');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> Waiting for Google...';

        showStatus('loading', 'Opening Google sign-in popup…');

        // Use Google Identity Services (GIS) to get id_token
        google.accounts.id.initialize({
            client_id: GOOGLE_CLIENT_ID,
            callback: onGoogleResponse,
            auto_select: false,
        });

        // Trigger One Tap or popup
        google.accounts.id.prompt((notification) => {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                // Fallback: use the OAuth2 code flow popup for access_token
                const client = google.accounts.oauth2.initTokenClient({
                    client_id: GOOGLE_CLIENT_ID,
                    scope: 'openid email profile',
                    callback: (tokenResponse) => {
                        if (tokenResponse.access_token) {
                            showStatus('loading', 'Got access_token from Google, calling API…');
                            showToken('Google access_token', tokenResponse.access_token);
                            callSocialApi('google', { access_token: tokenResponse.access_token });
                        } else {
                            resetGoogleBtn();
                            showStatus('error', 'Google sign-in was cancelled.');
                        }
                    },
                });
                client.requestAccessToken();
            }
        });
    }

    function onGoogleResponse(response) {
        if (response.credential) {
            showStatus('loading', 'Got id_token from Google, calling API…');
            showToken('Google id_token', response.credential);
            callSocialApi('google', { id_token: response.credential });
        } else {
            resetGoogleBtn();
            showStatus('error', 'Google sign-in failed — no credential returned.');
        }
    }

    function resetGoogleBtn() {
        const btn = document.getElementById('btn-google');
        btn.disabled = false;
        btn.innerHTML = `
            <svg viewBox="0 0 24 24" width="20" height="20">
                <path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.97h3.86c2.26-2.09 3.56-5.17 3.56-8.79z"/>
                <path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-2.97c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.07C3.515 21.27 7.565 24 12.255 24z"/>
                <path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.64h-3.98a11.86 11.86 0 000 10.72l3.98-3.07z"/>
                <path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.69 0-8.74 2.73-10.71 6.64l3.98 3.07c.95-2.85 3.6-4.96 6.73-4.96z"/>
            </svg>
            Sign in with Google`;
    }

    // ─── LinkedIn Sign-In (OAuth 2.0 Authorization Code flow via popup) ──
    let linkedInPopup = null;

    function handleLinkedInLogin() {
        if (!LINKEDIN_CLIENT_ID) return;

        const btn = document.getElementById('btn-linkedin');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> Waiting for LinkedIn...';

        showStatus('loading', 'Opening LinkedIn authorization window…');

        const redirectUri = window.location.origin + '/auth/linkedin/callback';
        const state = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2,'0')).join('');
        sessionStorage.setItem('li_state', state);

        const authUrl = 'https://www.linkedin.com/oauth/v2/authorization?' + new URLSearchParams({
            response_type: 'code',
            client_id: LINKEDIN_CLIENT_ID,
            redirect_uri: redirectUri,
            state: state,
            scope: 'openid profile email',
        });

        const w = 600, h = 700;
        const left = (screen.width - w) / 2;
        const top = (screen.height - h) / 2;
        linkedInPopup = window.open(authUrl, 'linkedin_auth', `width=${w},height=${h},left=${left},top=${top}`);

        // Poll for popup close (in case user closes it manually)
        const poll = setInterval(() => {
            if (linkedInPopup && linkedInPopup.closed) {
                clearInterval(poll);
                // A small delay to let the message arrive
                setTimeout(() => {
                    if (document.getElementById('btn-linkedin').disabled) {
                        resetLinkedInBtn();
                        showStatus('error', 'LinkedIn sign-in window was closed.');
                    }
                }, 500);
            }
        }, 500);
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;

        if (event.data.type === 'linkedin_callback') {
            if (linkedInPopup) linkedInPopup.close();

            const savedState = sessionStorage.getItem('li_state');
            if (event.data.state !== savedState) {
                resetLinkedInBtn();
                showStatus('error', 'LinkedIn OAuth state mismatch. Try again.');
                return;
            }

            if (event.data.code) {
                showStatus('loading', 'Got authorization code, calling API…');
                showToken('LinkedIn authorization code', event.data.code);
                const redirectUri = window.location.origin + '/auth/linkedin/callback';
                callSocialApi('linkedin', { code: event.data.code, redirect_uri: redirectUri });
            } else {
                resetLinkedInBtn();
                showStatus('error', 'LinkedIn authorization failed: ' + (event.data.error || 'unknown error'));
            }
        }
    });

    function resetLinkedInBtn() {
        const btn = document.getElementById('btn-linkedin');
        btn.disabled = false;
        btn.innerHTML = `
            <svg viewBox="0 0 24 24" fill="#0a66c2" width="20" height="20">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
            </svg>
            Sign in with LinkedIn`;
    }

