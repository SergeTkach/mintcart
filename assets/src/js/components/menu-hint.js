/**
 * mc-menu-hint behavior: an interactive "this is where you will find it"
 * pointer in the admin menu.
 *
 * The Setup Wizard opens some screens in a separate tab (Business > Delivery,
 * Business > Payments) and appends `mc_hint=1` to their URL. On such a page
 * this opens the menu group the screen lives in, marks the screen's own menu
 * item and shows a short bubble next to it. A screen that has no menu item of
 * its own (a payment extension's settings, say) can name the item to point at
 * instead: `mc_hint=marketplace/extension` marks Extensions > Extensions.
 * The bubble says "Next time you will find this here: Business > Delivery"
 * and is dismissed with its button, with Escape or by clicking anywhere else.
 *
 * `mc_point=<route>` also marks the button on the page itself that leads to
 * that route (a list's pencil that opens the settings, say) with its own
 * bubble ("Click here to open it").
 *
 * The texts come from `#column-left` (`data-mc-hint-text`, `data-mc-hint-ok`,
 * `data-mc-hint-click`) so they are translated with the rest of the menu.
 */

function findItem(menu, route) {
    return [...menu.querySelectorAll('a[href]')].find((link) => {
        try {
            return new URL(link.href, window.location.href).searchParams.get('route') === route;
        } catch (error) {
            return false;
        }
    });
}

function openAncestors(link) {
    // The legacy admin menu is Bootstrap 3 collapse: "ul.collapse.in" is open.
    for (let list = link.closest('ul.collapse'); list; list = list.parentElement?.closest('ul.collapse')) {
        list.classList.add('in');
        list.setAttribute('aria-expanded', 'true');
        document.querySelector(`#menu a[href="#${list.id}"]`)?.classList.remove('collapsed');
    }
}

function pathOf(link) {
    const names = [link.textContent.trim()];

    for (let list = link.closest('ul.collapse'); list; list = list.parentElement?.closest('ul.collapse')) {
        const parent = document.querySelector(`#menu a[href="#${list.id}"]`);

        if (parent) {
            names.unshift(parent.textContent.trim());
        }
    }

    return names.join(' › ');
}

function createPointer(target, className, side, message, okLabel) {
    target.classList.add(className);

    const bubble = document.createElement('div');
    bubble.className = `mc-menu-hint__bubble mc-menu-hint__bubble--${side}`;
    bubble.setAttribute('role', 'status');

    const text = document.createElement('p');
    text.className = 'mc-menu-hint__text';
    text.append(...message);

    const ok = document.createElement('button');
    ok.type = 'button';
    ok.className = 'mc-menu-hint__ok';
    ok.textContent = okLabel;

    bubble.append(text, ok);
    document.body.append(bubble);

    const place = () => {
        const box = target.getBoundingClientRect();

        if (side === 'right') {
            // Beside the item, but never past the bottom edge of the window.
            const top = Math.min(box.top - 6, window.innerHeight - bubble.offsetHeight - 8);

            bubble.style.top = `${Math.max(top, 8) + window.scrollY}px`;
            bubble.style.left = `${box.right + window.scrollX + 14}px`;
        } else {
            // Under the element, its right edge level with the element's.
            bubble.style.top = `${box.bottom + window.scrollY + 12}px`;
            bubble.style.left = `${Math.max(box.right - bubble.offsetWidth, 8) + window.scrollX}px`;
        }
    };

    place();

    return {
        bubble,
        ok,
        place,
        remove() {
            bubble.remove();
            target.classList.remove(className);
        },
    };
}

export function initMenuHint() {
    const params = new URLSearchParams(window.location.search);
    const menu = document.getElementById('column-left');
    const hint = params.get('mc_hint');
    const point = params.get('mc_point');

    if (!menu || (!hint && !point)) {
        return;
    }

    const pointers = [];

    // 1. The menu item the screen lives under.
    const link = hint ? findItem(menu, hint === '1' ? params.get('route') : hint) : null;

    if (link) {
        openAncestors(link);

        // On a narrow screen the whole menu is hidden: nothing to point at.
        if (link.getClientRects().length > 0) {
            link.scrollIntoView({ block: 'nearest' });

            const path = document.createElement('strong');
            path.textContent = pathOf(link);

            pointers.push(createPointer(link, 'mc-menu-hint', 'right', [`${menu.dataset.mcHintText} `, path], menu.dataset.mcHintOk));
        }
    }

    // 2. The button on this page that opens the thing itself.
    const button = point ? findItem(document.getElementById('content') || document, point) : null;

    if (button && button.getClientRects().length > 0) {
        pointers.push(createPointer(button, 'mc-point-hint', 'below', [menu.dataset.mcHintClick], menu.dataset.mcHintOk));
    }

    if (!pointers.length) {
        return;
    }

    const place = () => pointers.forEach((pointer) => pointer.place());

    const dismiss = () => {
        pointers.forEach((pointer) => pointer.remove());
        window.removeEventListener('resize', place);
        document.removeEventListener('keydown', onKey);
        document.removeEventListener('click', onClick);
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            dismiss();
        }
    };

    const onClick = (event) => {
        const inside = pointers.some((pointer) => pointer.bubble.contains(event.target));

        if (!inside || pointers.some((pointer) => event.target === pointer.ok)) {
            dismiss();
        }
    };

    window.addEventListener('resize', place);
    document.addEventListener('keydown', onKey);
    // A click that opened the page must not close the bubbles at once.
    setTimeout(() => document.addEventListener('click', onClick), 0);

    // Drop only the hints from the address. Re-serialising the query with
    // URLSearchParams would turn the route's "/" into "%2F".
    const search = window.location.search
        .replace(/([?&])mc_(hint|point)=[^&]*&?/g, '$1')
        .replace(/[?&]$/, '');

    window.history.replaceState(null, '', `${window.location.pathname}${search}${window.location.hash}`);
}
