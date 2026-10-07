/**
 * Sticky header support (`[data-mc-sticky-header]`, mc-header--sticky in
 * header.css): keeps --mc-header-topbar-height in sync with the topbar's
 * real height (it wraps on narrow screens, fonts load late) and toggles
 * `is-stuck` once the topbar has scrolled away, for the shadow.
 */
export function initStickyHeader(root = document) {
    const header = root.querySelector('[data-mc-sticky-header]');

    if (!header) {
        return;
    }

    const topbar = header.querySelector('.mc-header__topbar');
    let offset = 0;
    let frame = 0;

    const update = () => {
        frame = 0;
        header.classList.toggle('is-stuck', window.scrollY > offset);
    };

    const measure = () => {
        offset = topbar ? topbar.offsetHeight : 0;
        header.style.setProperty('--mc-header-topbar-height', `${offset}px`);
        update();
    };

    if (topbar && typeof ResizeObserver === 'function') {
        new ResizeObserver(measure).observe(topbar);
    }

    window.addEventListener('scroll', () => {
        if (!frame) {
            frame = requestAnimationFrame(update);
        }
    }, { passive: true });

    measure();
}
