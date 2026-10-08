/**
 * mc-section-nav active-section highlight (`[data-mc-section-nav]`,
 * section-nav.css).
 *
 * The links are `href="<page url>#id"` - the full URL, because OpenCart's
 * <base href> makes a bare "#id" point at the home page; without JS they
 * still land on the section (via a reload). This module:
 * - scrolls to the section in place on click (smooth via Bootstrap's
 *   :root scroll-behavior, offset by scroll-padding-top);
 * - measures the bar into --mc-section-nav-height on <html>, which that
 *   scroll-padding-top uses;
 * - moves `aria-current="true"` to the link of the `[data-mc-section]`
 *   currently under the bar (scroll spy).
 * Other links to a section (e.g. the rating under the title) get the same
 * in-place scroll when they carry `data-mc-action="scroll-to-section"`.
 */
export function initSectionNav(root = document) {
    const nav = root.querySelector('[data-mc-section-nav]');

    if (!nav) {
        return;
    }

    const targetId = (link) => new URL(link.href).hash.slice(1);
    const links = [...nav.querySelectorAll('a[href*="#"]')];
    const sections = links
        .map((link) => document.getElementById(targetId(link)))
        .filter(Boolean);

    if (!sections.length) {
        return;
    }

    let current = null;

    const setActive = (id) => {
        if (id === current) {
            return;
        }

        current = id;
        links.forEach((link) => {
            if (targetId(link) === id) {
                link.setAttribute('aria-current', 'true');
                // Keep the active item visible in the horizontally scrolling
                // bar on phones, without moving the page itself.
                const list = link.closest('ul');

                if (list && list.scrollWidth > list.clientWidth) {
                    list.scrollLeft = link.offsetLeft - (list.clientWidth - link.offsetWidth) / 2;
                }
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    // Runs straight from the scroll listener, as in buy-bar.js: browsers
    // already deliver scroll events at most once per frame and the check is
    // a handful of getBoundingClientRect() calls.
    const update = () => {
        // A section is "current" once its top passes the bottom edge of the
        // bar (plus a little slack, so an anchor jump lands on its own link).
        const line = nav.getBoundingClientRect().bottom + 24;
        let active = sections[0].id;

        sections.forEach((section) => {
            if (section.getBoundingClientRect().top <= line) {
                active = section.id;
            }
        });

        // The last section may be too short to ever reach the line - pick it
        // once the page is scrolled to the very end.
        const atBottom = window.scrollY > 0
            && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;

        if (atBottom) {
            active = sections[sections.length - 1].id;
        }

        setActive(active);
    };

    const measure = () => {
        document.documentElement.style.setProperty(
            '--mc-section-nav-height',
            `${nav.offsetHeight}px`
        );
        update();
    };

    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(measure).observe(nav);
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest(
            '[data-mc-section-nav] a[href*="#"], a[data-mc-action="scroll-to-section"]'
        );
        const section = link && document.getElementById(targetId(link));

        if (!section || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        section.scrollIntoView({ block: 'start' });
    });

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    measure();
}
