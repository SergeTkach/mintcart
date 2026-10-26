/**
 * Design > Styles (admin): Pickr colour pickers kept in step with their hex fields,
 * and the live preview painted from the form.
 *
 * The preview is an approximation of the storefront drawn with its own
 * `--pv-*` variables. The token values here repeat the ones in
 * `MintCart\Design\StyleOverrides` (PHP) and the preset colours come from the
 * checked look tile's `data-swatches` (primary, text, page tint), so change
 * them together.
 *
 * Markup contract: `docs/developer/theme-api/styles-screen.md`
 */

import Pickr from '@simonwep/pickr';

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
        // One Pickr per colour button; the hex text field stays the value that is posted.
        const pickers = {};

        form.querySelectorAll('[data-mc-design-color]').forEach((button) => {
            const key = button.dataset.mcDesignColor;
            const pickr = Pickr.create({
                el: button,
                useAsButton: true,
                theme: 'nano',
                default: normalizeHex(hexField(key).value) || '#059669',
                defaultRepresentation: 'HEX',
                components: {
                    preview: true,
                    opacity: false,
                    hue: true,
                    interaction: { hex: true, input: true, save: true },
                },
                i18n: { 'btn:save': button.dataset.labelSave || 'OK' },
            });

            pickr.on('change', (color) => {
                hexField(key).value = color.toHEXA().toString().slice(0, 7).toLowerCase();
                paint();
            });
            pickr.on('save', () => pickr.hide());

            pickers[key] = pickr;
        });

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

            markPalettes();

            ['primary', 'accent'].forEach((key) => {
                const hex = normalizeHex(hexField(key).value);

                if (hex) {
                    pickers[key]?.setColor(hex, true);
                    form.querySelector(`[data-mc-design-color="${key}"]`)?.style.setProperty('--pcr-color', hex);
                }
            });
        }

        // Picking a look sets every control to what that look uses; tweak from there.
        function applyLook(defaults) {
            ['primary', 'accent'].forEach((key) => {
                hexField(key).value = defaults[key] || '';
            });

            ['radius', 'border', 'button_style', 'container'].forEach((name) => {
                const radio = form.querySelector(`[data-mc-design-field="${name}"][value="${defaults[name] ?? ''}"]`);

                if (radio) {
                    radio.checked = true;
                }
            });

            ['font_heading', 'font_body'].forEach((name) => {
                const select = form.querySelector(`select[data-mc-design-field="${name}"]`);

                if (select) {
                    select.value = defaults[name] || 'system';
                }
            });
        }

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-mc-design-preset]')) {
                try {
                    applyLook(JSON.parse(event.target.dataset.defaults || '{}'));
                } catch {
                    // a malformed attribute leaves the controls as they are
                }
            }

            paint();
        });

        form.addEventListener('input', (event) => {
            if (event.target.closest('[data-mc-design-hex]')) {
                paint();
            }
        });

        function markPalettes() {
            const primary = normalizeHex(hexField('primary').value);
            const accent = normalizeHex(hexField('accent').value);

            form.querySelectorAll('[data-mc-design-palette]').forEach((tile) => {
                const active = primary === tile.dataset.primary && accent === tile.dataset.accent;

                tile.classList.toggle('is-active', active);
                tile.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        form.addEventListener('click', (event) => {
            const palette = event.target.closest('[data-mc-design-palette]');

            if (palette) {
                hexField('primary').value = palette.dataset.primary;
                hexField('accent').value = palette.dataset.accent;
                paint();

                return;
            }

            const reset = event.target.closest('[data-mc-design-reset]');

            if (reset) {
                try {
                    const defaults = JSON.parse(form.querySelector('[data-mc-design-preset]:checked')?.dataset.defaults || '{}');

                    hexField(reset.dataset.mcDesignReset).value = defaults[reset.dataset.mcDesignReset] || '';
                } catch {
                    hexField(reset.dataset.mcDesignReset).value = '';
                }

                paint();
            }
        });

        paint();
    });
}
