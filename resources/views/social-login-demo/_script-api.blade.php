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
