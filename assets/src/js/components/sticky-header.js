/**
 * Sticky header support (`[data-mc-sticky-header]`, mc-header--sticky in
 * header.css): keeps --mc-header-topbar-height in sync with the topbar's (and
 * announcement bar's) real height (it wraps on narrow screens, fonts load late) and toggles
 * `is-stuck` once the topbar has scrolled away, for the shadow. Also
 * publishes the height of the part that stays stuck as
 * --mc-header-sticky-offset on <html>, for things that stick under it
 * (mc-section-nav, the cart summary).
 */
export function initStickyHeader(root = document) {
    const header = root.querySelector('[data-mc-sticky-header]');

    if (!header) {
        return;
    }

    // Everything above the main row scrolls away: the optional announcement
    // bar plus the topbar.
    const bars = [
        header.querySelector('.mc-header__announcement'),
        header.querySelector('.mc-header__topbar'),
    ].filter(Boolean);
    let offset = 0;
    let frame = 0;

    const update = () => {
        frame = 0;
        header.classList.toggle('is-stuck', window.scrollY > offset);
    };

    const measure = () => {
        offset = bars.reduce((sum, bar) => sum + bar.offsetHeight, 0);
        header.style.setProperty('--mc-header-topbar-height', `${offset}px`);
        document.documentElement.style.setProperty(
            '--mc-header-sticky-offset',
            `${header.offsetHeight - offset}px`
        );
        update();
    };

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(measure);

        observer.observe(header);

        bars.forEach((bar) => observer.observe(bar));
    }

    window.addEventListener('scroll', () => {
        if (!frame) {
            frame = requestAnimationFrame(update);
        }
    }, { passive: true });

    measure();
}
