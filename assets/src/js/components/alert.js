/**
 * mc-alert dismiss behavior.
 *
 * Behavior hook (CLAUDE.md §14): `[data-mc-action="dismiss-alert"]` inside
 * `.mc-alert`. Delegated on `document` so alerts rendered after page load
 * (e.g. inserted via fetch()) are dismissible without re-wiring - no
 * per-instance init call needed at render time.
 *
 * Contract: docs/developer/components/alert.md
 */

export function initAlertDismiss(root = document) {
    root.addEventListener('click', (event) => {
        const dismissButton = event.target.closest('[data-mc-action="dismiss-alert"]');

        if (!dismissButton) {
            return;
        }

        const alert = dismissButton.closest('.mc-alert');

        if (!alert) {
            return;
        }

        alert.dispatchEvent(new CustomEvent('mc:alert:dismiss', { bubbles: true }));
        alert.remove();
    });
}
