// ── Save Item (inline, for generic sections) ──
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.save-item-btn');
    if (!btn) return;

    const card = btn.closest('.item-card');
    const id = card.dataset.id;
    const data = {};

    card.querySelectorAll('[data-field]').forEach(input => {
        data[input.dataset.field] = input.value;
    });

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>';

    try {
        const res = await fetch('/admin/content/items/' + id, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ data })
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

// ── Settings image preview ──
function previewSettingsImage(input, key) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('preview-' + key);
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Item image upload (AJAX) ──
document.addEventListener('change', async (e) => {
    const input = e.target.closest('.item-image-input');
    if (!input || !input.files[0]) return;

    const card = input.closest('.item-card');
    const field = input.dataset.field;
    const wrapper = input.closest('.image-upload-inline');
    const thumbWrap = wrapper.querySelector('.thumb-wrap');

    const formData = new FormData();
    formData.append('image', input.files[0]);

    try {
        const res = await fetch('/admin/content/upload-image', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            const hidden = card.querySelector(`input[data-field="${field}"]`);
            if (hidden) hidden.value = result.data.url;
            if (thumbWrap) {
                thumbWrap.innerHTML = `<img src="${result.data.url}" alt="" class="thumb">`;
            }
        }
    } catch (err) {
        console.error('Upload failed:', err);
    }
});
