<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Login Demo — {{ config('brand.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface-hover: #22263a;
            --border: #2a2e3e;
            --text: #e4e6ef;
            --text-muted: #8b8fa3;
            --accent: #2563EB;
            --accent-glow: rgba(37, 99, 235, 0.25);
            --google: #4285F4;
            --linkedin: #0a66c2;
            --success: #00b894;
            --error: #e17055;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 520px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 8px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #a29bfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .config-warning {
            background: rgba(225, 112, 85, 0.1);
            border: 1px solid rgba(225, 112, 85, 0.3);
            border-radius: var(--radius);
            padding: 14px 16px;
            font-size: 13px;
            color: var(--error);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .config-warning code {
            background: rgba(225, 112, 85, 0.15);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Social Buttons */
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
            color: var(--text);
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 12px;
        }

        .social-btn:hover {
            background: var(--surface-hover);
            border-color: #3a3e50;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }

        .social-btn:active { transform: translateY(0); }

        .social-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }

        .social-btn svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .social-btn.google:hover {
            border-color: var(--google);
            box-shadow: 0 4px 16px rgba(66, 133, 244, 0.15);
        }

        .social-btn.linkedin:hover {
            border-color: var(--linkedin);
            box-shadow: 0 4px 16px rgba(10, 102, 194, 0.15);
        }

        /* Spinner */
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Status area */
        .status {
            margin-top: 20px;
            display: none;
        }

        .status.show { display: block; }

        .status-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-header.success { color: var(--success); }
        .status-header.error { color: var(--error); }
        .status-header.loading { color: var(--accent); }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-dot.success { background: var(--success); box-shadow: 0 0 8px var(--success); }
        .status-dot.error { background: var(--error); box-shadow: 0 0 8px var(--error); }
        .status-dot.loading { background: var(--accent); box-shadow: 0 0 8px var(--accent); animation: pulse 1s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .token-box {
            background: #12141c;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .token-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .token-value {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 12px;
            color: var(--text);
            word-break: break-all;
            line-height: 1.6;
            max-height: 90px;
            overflow-y: auto;
        }

        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .copy-btn:hover {
            border-color: var(--accent);
            color: var(--text);
        }

        .copy-btn.copied {
            border-color: var(--success);
            color: var(--success);
        }

        /* API Response box */
        .response-box {
            background: #12141c;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 12px;
            overflow: hidden;
        }

        .response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border);
        }

        .response-header span {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .http-status {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .http-status.s2xx { background: rgba(0,184,148,0.15); color: var(--success); }
        .http-status.s4xx { background: rgba(225,112,85,0.15); color: var(--error); }
        .http-status.s5xx { background: rgba(214,48,49,0.15); color: #d63031; }

        .response-body {
            padding: 14px;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 12px;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            color: var(--text);
        }

        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .footer-note a {
            color: var(--accent);
            text-decoration: none;
        }
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

    // ─── Call Backend Social API ────────────────────────────────
    async function callSocialApi(provider, tokenPayload) {
        try {
            const res = await fetch(API_BASE + '/auth/social', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ provider, ...tokenPayload }),
            });

            const data = await res.json();

            if (provider === 'google') resetGoogleBtn();
            else resetLinkedInBtn();

            showApiResponse(res.status, data);

            if (res.ok) {
                showStatus('success', `Authenticated via ${provider}! Action: ${data.action || 'login'}`);
            } else {
                showStatus('error', `API returned ${res.status}`);
            }
        } catch (e) {
            if (provider === 'google') resetGoogleBtn();
            else resetLinkedInBtn();
            showStatus('error', 'Network error calling API: ' + e.message);
        }
    }

    // ─── UI Helpers ────────────────────────────────────────────
    function showStatus(type, message) {
        const el = document.getElementById('status');
        el.classList.add('show');

        // Preserve existing token boxes and response boxes
        const existing = el.querySelectorAll('.token-box, .response-box');
        const saved = Array.from(existing).map(e => e.outerHTML).join('');

        el.innerHTML = `
            <div class="status-header ${type}">
                <span class="status-dot ${type}"></span>
                ${message}
            </div>
            ${saved}
        `;
    }

    function showToken(label, value) {
        const el = document.getElementById('status');
        el.classList.add('show');

        const id = 'tok-' + Date.now();
        const box = document.createElement('div');
        box.className = 'token-box';
        box.innerHTML = `
            <div class="token-label">${label}</div>
            <div class="token-value" id="${id}">${value}</div>
            <div style="margin-top:8px">
                <button class="copy-btn" onclick="copyToken('${id}', this)">
                    📋 Copy token
                </button>
            </div>
        `;
        el.appendChild(box);
    }

    function showApiResponse(status, data) {
        const el = document.getElementById('status');
        const statusClass = status < 300 ? 's2xx' : status < 500 ? 's4xx' : 's5xx';

        const box = document.createElement('div');
        box.className = 'response-box';
        box.innerHTML = `
            <div class="response-header">
                <span>API Response</span>
                <span class="http-status ${statusClass}">${status}</span>
            </div>
            <div class="response-body">${JSON.stringify(data, null, 2)}</div>
        `;
        el.appendChild(box);
    }

    function copyToken(id, btn) {
        const text = document.getElementById(id).textContent;
        navigator.clipboard.writeText(text).then(() => {
            btn.classList.add('copied');
            btn.innerHTML = '✅ Copied!';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '📋 Copy token';
            }, 2000);
        });
    }
</script>

</body>
</html>
