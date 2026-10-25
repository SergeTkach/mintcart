/**
 * Design > Styles (admin): keeps the colour picker and the hex field in step,
 * and paints the live preview from the form.
 *
 * The preview is an approximation of the storefront drawn with its own
 * `--pv-*` variables. The token values here repeat the ones in
 * `MintCart\Design\StyleOverrides` (PHP) and the preset colours come from the
 * checked look tile's `data-swatches` (primary, text, page tint), so change
 * them together.
 *
 * Markup contract: `docs/developer/theme-api/styles-screen.md`
 */

const RADIUS = {
    sharp: ['0', '0'],
    soft: ['0.375rem', '0.5rem'],
    rounded: ['0.75rem', '1rem'],
};

const FALLBACK = { primary: '#059669', text: '#212529', tint: '#f8f9fa' };

function luminance(hex) {
    const channel = (pair) => {
        const c = parseInt(pair, 16) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(hex.slice(1, 3)) + 0.7152 * channel(hex.slice(3, 5)) + 0.0722 * channel(hex.slice(5, 7));
}

function onColor(hex) {
    const l = luminance(hex);

    return 1.05 / (l + 0.05) >= (l + 0.05) / 0.0685 ? '#ffffff' : '#111111';
}

function normalizeHex(value) {
    const v = value.trim().toLowerCase();

    if (/^#[0-9a-f]{6}$/.test(v)) {
        return v;
    }

    const short = v.match(/^#([0-9a-f])([0-9a-f])([0-9a-f])$/);

    return short ? `#${short[1]}${short[1]}${short[2]}${short[2]}${short[3]}${short[3]}` : '';
}

export function initDesignStyles(context = document) {
    context.querySelectorAll('[data-mc-design-styles]').forEach((form) => {
        const preview = form.querySelector('[data-mc-design-preview]');

        if (!preview) {
            return;
        }

        const value = (name) => form.querySelector(`[data-mc-design-field="${name}"]:checked`)?.value
            ?? form.querySelector(`select[data-mc-design-field="${name}"]`)?.value
            ?? '';

        const hexField = (key) => form.querySelector(`[data-mc-design-hex="${key}"]`);
        const picker = (key) => form.querySelector(`[data-mc-design-color="${key}"]`);

        function paint() {
            const checked = form.querySelector('[data-mc-design-preset]:checked');
            const swatches = (checked?.dataset.swatches || '').split(',').filter(Boolean);
            const base = swatches.length === 3
                ? { primary: swatches[0], text: swatches[1], tint: swatches[2] }
                : FALLBACK;

            const primary = normalizeHex(hexField('primary').value) || base.primary;
            const accent = normalizeHex(hexField('accent').value) || '#0d6efd';
            const set = (name, v) => preview.style.setProperty(name, v);

            set('--pv-primary', primary);
            set('--pv-on-primary', onColor(primary));
            set('--pv-accent', accent);
            set('--pv-on-accent', onColor(accent));
            set('--pv-text', base.text);
            set('--pv-tint', base.tint);

            const radius = RADIUS[value('radius')] || RADIUS.soft;

            set('--pv-radius', radius[0]);
            set('--pv-radius-lg', radius[1]);

            const border = value('border');

            set('--pv-border-width', border === 'strong' ? '2px' : '1px');
            set('--pv-border', border === 'light' ? '#e4e7ea' : (border === 'strong' ? '#6c757d' : '#ced4da'));

            const shape = value('button_shape');

            set('--pv-button-radius', shape === 'pill' ? '999px' : (shape === 'square' ? '0' : 'var(--pv-radius-lg)'));

            const style = value('button_style');

            set('--pv-btn-bg', style === 'outline' ? 'transparent' : (style === 'soft' ? `color-mix(in srgb, ${primary} 12%, #fff)` : primary));
            set('--pv-btn-color', style === 'solid' || style === '' ? onColor(primary) : (style === 'soft' ? `color-mix(in srgb, ${primary} 85%, #000)` : primary));
            set('--pv-btn-border', style === 'outline' ? primary : 'transparent');

            ['font_body', 'font_heading'].forEach((key) => {
                const select = form.querySelector(`select[data-mc-design-field="${key}"]`);
                const stack = select?.selectedOptions[0]?.dataset.stack || '';

                set(key === 'font_body' ? '--pv-font-body' : '--pv-font-heading', stack);
            });

            if (!value('font_heading')) {
                const body = form.querySelector('select[data-mc-design-field="font_body"]')?.selectedOptions[0]?.dataset.stack;

                if (body) {
                    set('--pv-font-heading', body);
                }
            }

            ['primary', 'accent'].forEach((key) => {
                const hex = normalizeHex(hexField(key).value);

                if (hex) {
                    picker(key).value = hex;
                }
            });
        }

        form.addEventListener('change', (event) => {
            const color = event.target.closest('[data-mc-design-color]');

            if (color) {
                hexField(color.dataset.mcDesignColor).value = color.value;
            }

            paint();
        });

        form.addEventListener('input', (event) => {
            const color = event.target.closest('[data-mc-design-color]');

            if (color) {
                hexField(color.dataset.mcDesignColor).value = color.value;
            }

            if (color || event.target.closest('[data-mc-design-hex]')) {
                paint();
            }
        });

        form.addEventListener('click', (event) => {
            const reset = event.target.closest('[data-mc-design-reset]');

            if (reset) {
                hexField(reset.dataset.mcDesignReset).value = '';
                paint();
            }
        });

        paint();
    });
}
