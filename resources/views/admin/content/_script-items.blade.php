const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ── Tab Switching ──
document.querySelectorAll('.cms-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const group = tab.dataset.tab;
        document.querySelectorAll('.cms-tab').forEach(t => {
            const isActive = (t === tab);
            t.classList.toggle('is-active', isActive);
            t.style.borderColor = isActive ? 'var(--primary)' : 'transparent';
            t.style.color = isActive ? 'var(--primary)' : 'var(--text-muted)';
        });
        document.querySelectorAll('.cms-panel').forEach(p => p.classList.toggle('hidden', p.dataset.tab !== group));

        // Update URL so refresh stays on current tab
        const url = new URL(window.location);
        url.searchParams.set('tab', group);
        history.replaceState(null, '', url);

        // Show save button only for settings tabs
        const settingsTabs = @json(array_keys($settingsSections));
        document.getElementById('save-btn').style.display = settingsTabs.includes(group) ? '' : 'none';
    });
});

// Initial save button visibility
(function() {
    const settingsTabs = @json(array_keys($settingsSections));
    document.getElementById('save-btn').style.display = settingsTabs.includes('{{ $activeTab }}') ? '' : 'none';
})();

// ── Section field definitions (from PHP) ──
const sectionDefs = @json($itemsSections);

// ── Add Item (generic sections) ──
document.querySelectorAll('.add-item-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const section = btn.dataset.section;
        const def = sectionDefs[section];
        const data = {};
        for (const key of Object.keys(def.fields)) {
            data[key] = '';
        }

        btn.disabled = true;
        try {
            const res = await fetch('/admin/content/items', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ section, data })
            });
            const result = await res.json();
            if (result.success) {
                const url = new URL('/admin/content', window.location.origin);
                url.searchParams.set('tab', section);
                if (@json($context)) url.searchParams.set('context', @json($context));
                location.href = url.toString();
            }
        } catch (e) {
            console.error(e);
        } finally {
            btn.disabled = false;
        }
    });
});

// ── Delete Item ──
let pendingDeleteItem = null;

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.delete-item-btn');
    if (!btn) return;

    const id = btn.dataset.id;
    const section = btn.closest('.cms-panel')?.dataset.tab;
    pendingDeleteItem = { id, section };

    const modal = document.getElementById('delete-content-item-modal');
    const form = modal?.querySelector('form');
    if (form) {
        form.setAttribute('action', '/admin/content/items/' + encodeURIComponent(id));
    }

    if (typeof openConfirmModal === 'function') {
        openConfirmModal('delete-content-item-modal');
    }
});

document.getElementById('delete-content-item-modal')?.querySelector('form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!pendingDeleteItem) return;

    const id = pendingDeleteItem.id;
    const section = pendingDeleteItem.section;

    try {
        const res = await fetch('/admin/content/items/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await res.json();
        if (result.success) {
            const url = new URL('/admin/content', window.location.origin);
            url.searchParams.set('tab', section);
            if (@json($context)) url.searchParams.set('context', @json($context));
            location.href = url.toString();
            return;
        }

        if (window.AdminToast) {
            window.AdminToast.error(result.message || 'Could not delete item.');
        }
    } catch (error) {
        console.error(error);
        if (window.AdminToast) {
            window.AdminToast.error('Could not delete item.');
        }
    } finally {
        if (typeof closeConfirmModal === 'function') {
            closeConfirmModal('delete-content-item-modal');
        }
        pendingDeleteItem = null;
    }
});
