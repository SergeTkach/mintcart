/**
 * mc-progress behavior.
 *
 * Bootstrap 5's progress bar (`.progress`/`.progress-bar`) is CSS-only - it
 * has no JS class to wrap (unlike `mc-modal`/`mc-drawer`/`mc-toast`, which
 * wrap Bootstrap's `Modal`/`Offcanvas`/`Toast`). `mc-progress` instead owns a
 * small JS API of its own for the one thing static markup can't express: a
 * long-running operation (a chunked bulk-edit batch, an Update Manager
 * stage, a Setup Wizard step) updating the same bar's value over time.
 *
 * Single source of truth is `aria-valuenow`/`aria-valuemin`/`aria-valuemax`
 * on the `.mc-progress` root (Bootstrap 5's documented attribute placement -
 * the *outer* `.progress` element carries `role="progressbar"` and the
 * `aria-value*` triad, not the inner `.progress-bar`). The bar's inline
 * `width` is always derived from those attributes, never authored
 * separately, so a Twig template only has to set one number correctly.
 *
 * `initProgressBars()` performs that derivation once at load for any
 * `.mc-progress[aria-valuenow]` already in the markup (progressive
 * enhancement - a `style="width: ..."` fallback in the Twig contract keeps
 * the bar showing something correct even before this runs or if JS fails to
 * load). `setProgress()` is the exported imperative API a batch-job/update
 * flow calls after each chunk/stage completes.
 *
 * `.mc-progress` with no `aria-valuenow` at all is the indeterminate case
 * (unknown-duration operations - e.g. "Verifying backup") and is left alone
 * by `initProgressBars()`; its animated look is pure CSS (`--indeterminate`
 * modifier), see progress.css.
 *
 * Contract: docs/developer/components/progress.md
 */

function readBound(element, attribute, fallback) {
    const raw = element.getAttribute(attribute);

    if (raw === null || raw === '') {
        return fallback;
    }

    const value = Number(raw);

    return Number.isNaN(value) ? fallback : value;
}

function applyProgress(progress, value) {
    const bar = progress.querySelector('.mc-progress__bar');

    if (!bar) {
        return null;
    }

    const min = readBound(progress, 'aria-valuemin', 0);
    const max = readBound(progress, 'aria-valuemax', 100);
    const clamped = Math.min(max, Math.max(min, value));
    const ratio = max > min ? (clamped - min) / (max - min) : 0;

    bar.style.width = `${ratio * 100}%`;
    progress.setAttribute('aria-valuenow', String(clamped));

    const isComplete = clamped >= max;

    progress.classList.toggle('is-complete', isComplete);

    return { value: clamped, min, max, isComplete };
}

export function initProgressBars(root = document) {
    root.querySelectorAll('.mc-progress[aria-valuenow]').forEach((progress) => {
        applyProgress(progress, readBound(progress, 'aria-valuenow', 0));
    });
}

export function setProgress(progress, value, options = {}) {
    if (!progress) {
        return;
    }

    const wasComplete = progress.classList.contains('is-complete');
    const result = applyProgress(progress, value);

    if (!result) {
        return;
    }

    if (typeof options.label === 'string') {
        progress.setAttribute('aria-label', options.label);
    }

    progress.dispatchEvent(
        new CustomEvent('mc:progress:change', {
            bubbles: true,
            detail: { value: result.value, min: result.min, max: result.max },
        })
    );

    if (result.isComplete && !wasComplete) {
        progress.dispatchEvent(new CustomEvent('mc:progress:complete', { bubbles: true }));
    }
}
