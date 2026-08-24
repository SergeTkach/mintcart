/**
 * mc-sortable drag-and-drop reordering behavior.
 *
 * Wraps SortableJS 1.15.7 (`MINTCART_IMPLEMENTATION_GUIDE.md` §4/§45: use
 * SortableJS in admin for homepage blocks, categories, menus, banner items,
 * benefits, FAQ, gallery, footer columns - never jQuery UI Sortable) rather
 * than reimplementing drag reordering from scratch, the same allowance
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §9 gives `mc-modal`/`mc-drawer`/
 * `mc-toast` for wrapping Bootstrap's `Modal`/`Offcanvas`/`Toast`.
 *
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §68 and `MINTCART_FUNCTIONAL_SCOPE.md`
 * §27 both require a non-mouse alternative for sortable actions - pointer
 * drag alone is not sufficient. `[data-mc-action="sortable-move-up"]` /
 * `"sortable-move-down"` buttons (per `.mc-sortable__item`, wired here via
 * delegated click) give full keyboard/assistive-tech reordering without
 * depending on SortableJS's own drag simulation at all.
 *
 * Behavior hooks (CLAUDE.md §14):
 *   - `[data-mc-sortable]` on the list container - initializes SortableJS
 *     on its direct children.
 *   - `data-mc-sortable-handle` (boolean attribute) on the container -
 *     restricts dragging to `[data-mc-action="sortable-handle"]` elements
 *     inside each item, instead of the whole item being a drag surface.
 *   - `data-mc-sortable-item="<id>"` on each direct child - its value is
 *     the id reported in the reorder event's `detail.order`.
 *   - `[data-mc-action="sortable-move-up"]` / `"sortable-move-down"` inside
 *     an item - moves that item one position via the keyboard-usable path.
 *
 * After any reorder (drag or keyboard), a bubbling `mc:sortable:reorder`
 * `CustomEvent` fires on the container with
 * `detail: { order: string[] }` (`data-mc-sortable-item` values in the new
 * order) - the same event-contract pattern as `mc:cart:updated`
 * (`MINTCART_TECHNICAL_PLAN.md` §25), letting a consumer (e.g. a future
 * Homepage Builder or Categories drag-and-drop admin screen, Phase 10/17)
 * persist the new order with a single listener regardless of whether the
 * reorder came from a drag or a button click.
 *
 * Contract: docs/developer/components/sortable.md
 */

import Sortable from 'sortablejs';

const ITEM_SELECTOR = '[data-mc-sortable-item]';

function emitReorder(container) {
    const order = Array.from(container.children)
        .filter((child) => child.matches(ITEM_SELECTOR))
        .map((child) => child.getAttribute('data-mc-sortable-item'));

    container.dispatchEvent(
        new CustomEvent('mc:sortable:reorder', {
            bubbles: true,
            detail: { order },
        }),
    );
}

function updateMoveButtonsState(container) {
    const items = Array.from(container.children).filter((child) => child.matches(ITEM_SELECTOR));

    items.forEach((item, index) => {
        const moveUp = item.querySelector('[data-mc-action="sortable-move-up"]');
        const moveDown = item.querySelector('[data-mc-action="sortable-move-down"]');

        if (moveUp) {
            moveUp.disabled = index === 0;
        }

        if (moveDown) {
            moveDown.disabled = index === items.length - 1;
        }
    });
}

function moveItem(item, direction) {
    if (direction === 'up') {
        const previous = item.previousElementSibling;

        if (previous && previous.matches(ITEM_SELECTOR)) {
            item.parentNode.insertBefore(item, previous);
        }
    } else {
        const next = item.nextElementSibling;

        if (next && next.matches(ITEM_SELECTOR)) {
            item.parentNode.insertBefore(next, item);
        }
    }
}

const initializedContainers = new WeakSet();

export function initSortableLists(root = document) {
    root.querySelectorAll('[data-mc-sortable]').forEach((container) => {
        if (initializedContainers.has(container)) {
            return;
        }

        initializedContainers.add(container);

        Sortable.create(container, {
            animation: 150,
            ghostClass: 'is-dragging',
            chosenClass: 'is-chosen',
            handle: container.hasAttribute('data-mc-sortable-handle')
                ? '[data-mc-action="sortable-handle"]'
                : undefined,
            onEnd: () => {
                updateMoveButtonsState(container);
                emitReorder(container);
            },
        });

        updateMoveButtonsState(container);
    });

    root.addEventListener('click', (event) => {
        const moveButton = event.target.closest(
            '[data-mc-action="sortable-move-up"], [data-mc-action="sortable-move-down"]',
        );

        if (!moveButton) {
            return;
        }

        const item = moveButton.closest(ITEM_SELECTOR);
        const container = item ? item.parentElement : null;

        if (!item || !container || !container.hasAttribute('data-mc-sortable')) {
            return;
        }

        const direction = moveButton.getAttribute('data-mc-action') === 'sortable-move-up' ? 'up' : 'down';

        moveItem(item, direction);
        moveButton.focus();
        updateMoveButtonsState(container);
        emitReorder(container);
    });
}
