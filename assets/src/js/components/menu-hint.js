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
 * The texts come from `#column-left` (`data-mc-hint-text`, `data-mc-hint-ok`)
 * so they are translated with the rest of the menu.
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

export function initMenuHint() {
    const params = new URLSearchParams(window.location.search);
    const menu = document.getElementById('column-left');

    if (!menu || !params.get('mc_hint')) {
        return;
    }

    const hint = params.get('mc_hint');
    const link = findItem(menu, hint === '1' ? params.get('route') : hint);

    if (!link) {
        return;
    }

    openAncestors(link);

    // On a narrow screen the whole menu is hidden: nothing to point at.
    if (link.getClientRects().length === 0) {
        return;
    }

    link.classList.add('mc-menu-hint');
    link.scrollIntoView({ block: 'nearest' });

    const bubble = document.createElement('div');
    bubble.className = 'mc-menu-hint__bubble';
    bubble.setAttribute('role', 'status');

    const text = document.createElement('p');
    text.className = 'mc-menu-hint__text';
    text.append(`${menu.dataset.mcHintText} `);

    const path = document.createElement('strong');
    path.textContent = pathOf(link);
    text.append(path);

    const ok = document.createElement('button');
    ok.type = 'button';
    ok.className = 'mc-menu-hint__ok';
    ok.textContent = menu.dataset.mcHintOk;

    bubble.append(text, ok);
    document.body.append(bubble);

    const place = () => {
        const box = link.getBoundingClientRect();

        bubble.style.top = `${Math.max(box.top + window.scrollY - 6, 0)}px`;
        bubble.style.left = `${box.right + window.scrollX + 14}px`;
    };

    const dismiss = () => {
        bubble.remove();
        link.classList.remove('mc-menu-hint');
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
        if (!bubble.contains(event.target) || event.target === ok) {
            dismiss();
        }
    };

    place();
    window.addEventListener('resize', place);
    document.addEventListener('keydown', onKey);
    // A click that opened the page must not close the bubble at once.
    setTimeout(() => document.addEventListener('click', onClick), 0);

    params.delete('mc_hint');
    window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}${window.location.hash}`);
}
