/**
 * Links that ask before they act: `<a href="..." data-mc-confirm="Delete it?">`.
 * A cancelled confirm stops the navigation.
 */
export function initConfirmLinks() {
    document.addEventListener("click", (event) => {
        const link = event.target.closest("[data-mc-confirm]");

        if (link && !window.confirm(link.dataset.mcConfirm)) {
            event.preventDefault();
        }
    });
}
