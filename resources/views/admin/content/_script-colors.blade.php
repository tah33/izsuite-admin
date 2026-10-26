// ──────────────────────────────────────────
// ── Color Palette: Presets & HSL Inputs ──
// ──────────────────────────────────────────

const COLOR_PRESETS = {
    emerald: { primary: '160 60% 40%', accent: '38 92% 50%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    ocean:   { primary: '210 70% 50%', accent: '190 80% 45%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    purple:  { primary: '270 60% 55%', accent: '290 80% 50%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    crimson: { primary: '350 70% 50%', accent: '25 90% 55%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    amber:   { primary: '38 80% 50%', accent: '160 60% 40%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    slate:   { primary: '220 20% 45%', accent: '210 40% 55%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
};

function updateColorDots() {
    document.querySelectorAll('.color-hsl-input').forEach(input => {
        const dot = document.getElementById('dot-' + input.id);
        if (dot) {
            dot.style.background = `hsl(${input.value})`;
        }
    });
}

function highlightActivePreset(presetName) {
    document.querySelectorAll('.color-preset-btn').forEach(b => {
        const isActive = (b.dataset.preset === presetName);
        if (isActive) {
            b.style.borderColor = 'var(--primary)';
            b.style.color = 'var(--primary)';
            b.style.backgroundColor = 'var(--primary-light)';
            b.style.fontWeight = '600';
        } else {
            b.style.borderColor = '';
            b.style.color = '';
            b.style.backgroundColor = '';
            b.style.fontWeight = '';
        }
    });
}

function applyPreset(presetName) {
    const preset = COLOR_PRESETS[presetName];
    if (!preset) return;

    document.getElementById('color_preset').value = presetName;
    document.getElementById('color_primary').value = preset.primary;
    document.getElementById('color_accent').value = preset.accent;
    document.getElementById('color_destructive').value = preset.destructive;
    document.getElementById('color_success').value = preset.success;
    document.getElementById('color_warning').value = preset.warning;

    highlightActivePreset(presetName);
    updateColorDots();
}

// Preset button clicks
document.querySelectorAll('.color-preset-btn').forEach(btn => {
    btn.addEventListener('click', () => applyPreset(btn.dataset.preset));
});

// Live dot update on input change
document.querySelectorAll('.color-hsl-input').forEach(input => {
    input.addEventListener('input', updateColorDots);
});

// Highlight active preset on load
(function() {
    updateColorDots();
    const current = document.getElementById('color_preset')?.value || 'emerald';
    highlightActivePreset(current);
})();
