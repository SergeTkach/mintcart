/**
 * Keeps Bootstrap 5 off jQuery on the legacy OpenCart admin pages.
 *
 * `import 'bootstrap'` (Bootstrap 5) registers its jQuery plugins ($.fn.popover, .modal, .tooltip, ...)
 * when the DOM is ready, replacing the Bootstrap 3 ones the stock admin relies on - the file manager
 * (`$element.popover('destroy')` in admin/view/javascript/common.js) broke with
 * "No method named destroy". Bootstrap 5 skips the registration when <body> has `data-bs-no-jquery`;
 * this module (imported first in admin.js) sets it before Bootstrap's DOMContentLoaded handler runs.
 * MintCart's own admin code uses Bootstrap 5 through its ES API (new Modal(...)), not through jQuery.
 */
function mark() {
    document.body.setAttribute('data-bs-no-jquery', '');
}

if (document.body) {
    mark();
} else {
    document.addEventListener('DOMContentLoaded', mark, { once: true });
}
