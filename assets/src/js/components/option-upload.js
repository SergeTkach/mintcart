/**
 * File-type product options (product page). Same flow as stock OpenCart:
 * the picked file is POSTed to `tool/upload`, which returns an upload
 * `code`; that code - not the file - is the option value add-to-cart sends.
 *
 * Markup (mint_default product/product.twig):
 *   <input type="file" data-mc-option-upload="{id}" aria-describedby="{statusId}">
 *   <input type="hidden" name="option[{id}]" data-mc-option-id="{id}">
 *   <p id="{statusId}" aria-live="polite"></p>
 *
 * The hidden input is cleared as soon as a new file is picked, so a failed
 * or pending upload never leaves a stale code behind.
 */

function hiddenFor(input) {
    const id = input.dataset.mcOptionUpload;
    const scope = input.closest('[data-mc-product-options]') || document;

    return scope.querySelector(`input[type="hidden"][data-mc-option-id="${CSS.escape(id)}"]`);
}

async function upload(input) {
    const hidden = hiddenFor(input);
    const status = document.getElementById(input.getAttribute('aria-describedby') || '');
    const file = input.files && input.files[0];

    if (hidden) {
        hidden.value = '';
    }

    if (!file) {
        return;
    }

    const setStatus = (text, isError) => {
        if (status) {
            status.textContent = text;
            status.classList.toggle('has-error', isError);
        }
        input.setAttribute('aria-invalid', isError ? 'true' : 'false');
    };

    const body = new FormData();
    body.append('file', file);

    input.disabled = true;
    input.classList.add('is-loading');

    try {
        const response = await fetch('index.php?route=tool/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const json = await response.json();

        if (json.error || !json.code) {
            setStatus(json.error || 'Upload failed', true);
            return;
        }

        if (hidden) {
            hidden.value = json.code;
        }

        setStatus(json.success || file.name, false);
    } catch {
        setStatus('Upload failed', true);
    } finally {
        input.disabled = false;
        input.classList.remove('is-loading');
    }
}

export function initOptionUploads(root = document) {
    root.addEventListener('change', (event) => {
        const input = event.target.closest('[data-mc-option-upload]');

        if (input) {
            upload(input);
        }
    });
}
