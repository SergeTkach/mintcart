/**
 * mc-tabs show/hide behavior.
 *
 * Behavior hook (CLAUDE.md §14): `[data-mc-action="show-tab"]` on a tab
 * trigger, with Bootstrap's own `data-bs-target="<pane selector>"` kept as
 * required internal wiring (Bootstrap's `Tab` class reads it directly to
 * find the pane, the same way `mc-modal` keeps `.modal-dialog` as internal
 * plumbing - see tabs.css header comment). Delegated on `document`, same
 * approach as mc-modal/mc-alert - no per-instance init call needed at
 * render time.
 *
 * Drives Bootstrap 5's `Tab` class directly instead of relying on
 * Bootstrap's own `data-bs-toggle="tab"` attribute, so the only documented
 * public trigger contract is `data-mc-*` (CLAUDE.md §14). Reusing `Tab`
 * also gets the arrow-key/Home/End tablist navigation Bootstrap's component
 * implements for free (`MINTCART_FUNCTIONAL_SCOPE.md` §27 keyboard
 * navigation), instead of reimplementing it.
 *
 * Bootstrap's `shown.bs.tab`/`hidden.bs.tab` events are re-dispatched as
 * bubbling `mc:tabs:shown`/`mc:tabs:hidden` `CustomEvent`s on the `.mc-tabs`
 * root so extension code only needs the `mc:*` event contract
 * (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's internal event
 * namespace.
 *
 * Contract: docs/developer/components/tabs.md
 */

import { Tab } from 'bootstrap';

const relayedTabs = new WeakSet();

function relayTabEvents(tabs) {
    if (relayedTabs.has(tabs)) {
        return;
    }

    relayedTabs.add(tabs);

    tabs.addEventListener('shown.bs.tab', (event) => {
        tabs.dispatchEvent(new CustomEvent('mc:tabs:shown', {
            bubbles: true,
            detail: { pane: event.target.getAttribute('data-bs-target') },
        }));
    });

    tabs.addEventListener('hidden.bs.tab', (event) => {
        tabs.dispatchEvent(new CustomEvent('mc:tabs:hidden', {
            bubbles: true,
            detail: { pane: event.target.getAttribute('data-bs-target') },
        }));
    });
}

export function initTabsTriggers(root = document) {
    root.querySelectorAll('.mc-tabs').forEach(relayTabEvents);

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-mc-action="show-tab"]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        const tabs = trigger.closest('.mc-tabs');

        if (tabs) {
            relayTabEvents(tabs);
        }

        Tab.getOrCreateInstance(trigger).show();
    });
}
