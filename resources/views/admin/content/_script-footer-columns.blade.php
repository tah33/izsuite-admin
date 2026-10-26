// ──────────────────────────────────────────
// ── Footer Columns: Custom UI Handlers ──
// ──────────────────────────────────────────

function createLinkRow(title = '', url = '') {
    const row = document.createElement('div');
    row.className = 'col-link-row flex items-center gap-2';
    row.innerHTML = `
        <input type="text" class="form-input flex-1 link-title" value="${title}" placeholder="Link title">
        <input type="text" class="form-input flex-1 link-url" value="${url}" placeholder="URL e.g. /about">
        <button type="button" class="remove-link-btn btn-icon-danger flex-shrink-0" title="Remove">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </button>
    `;
    return row;
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.add-link-btn');
    if (!btn) return;
    const card = btn.closest('.footer-column-card');
    const list = card.querySelector('.col-links-list');
    list.appendChild(createLinkRow());
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.remove-link-btn');
    if (!btn) return;
    btn.closest('.col-link-row').remove();
});

document.getElementById('add-column-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('add-column-btn');
    btn.disabled = true;
    try {
        const res = await fetch('/admin/content/items', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ section: 'footer_columns', data: { name: '', links: [] } })
        });
        const result = await res.json();
        if (result.success) {
            const url = new URL('/admin/content', window.location.origin);
            url.searchParams.set('tab', 'footer_columns');
            if (@json($context)) url.searchParams.set('context', @json($context));
            location.href = url.toString();
        }
    } catch (e) {
        console.error(e);
    } finally {
        btn.disabled = false;
    }
});

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.save-column-btn');
    if (!btn) return;

    const card = btn.closest('.footer-column-card');
    const id = card.dataset.id;
    const name = card.querySelector('.col-name-input').value;
    const links = [];

    card.querySelectorAll('.col-link-row').forEach(row => {
        const title = row.querySelector('.link-title').value.trim();
        const url = row.querySelector('.link-url').value.trim();
        if (title || url) {
            links.push({ title, url });
        }
    });

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>';

    try {
        const res = await fetch('/admin/content/items/' + id, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ data: { name, links } })
        });
        const result = await res.json();
        if (result.success) {
            btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5"></i>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(() => {
                btn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 1500);
        }
    } catch (e) {
        console.error(e);
        btn.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5"></i>';
    } finally {
        btn.disabled = false;
    }
});
