/**
 * Phone input mask. The mask comes from `<html data-mc-phone-mask="+380 (##) ###-##-##">`
 * (store country default or the format set in Business > Contacts); `#` is one digit.
 * No attribute = inputs stay free-form.
 *
 * Applies to every `input[type="tel"]` and `input[name$="telephone"]` on the page,
 * including ones inserted later (Quick Order modal, checkout), through one delegated
 * listener. Typing the country code or a leading trunk `0` is tolerated, and pasting
 * a full number works.
 */

const SELECTOR = 'input[type="tel"], input[name$="telephone"]';

function getMask() {
    return document.documentElement.getAttribute('data-mc-phone-mask') || '';
}

/** Digits of the literal part before the first `#`, e.g. "380" for "+380 (##) ...". */
function countryDigits(mask) {
    return mask.slice(0, mask.indexOf('#')).replace(/\D/g, '');
}

export function nationalDigits(value, mask) {
    const prefix = countryDigits(mask);
    let digits = value.replace(/\D/g, '');

    if (prefix && digits.startsWith(prefix)) {
        digits = digits.slice(prefix.length);
    } else if (digits.startsWith('0') && prefix !== '1' && prefix !== '7') {
        digits = digits.slice(1);
    }

    return digits;
}

export function formatPhone(value, mask) {
    const digits = nationalDigits(value, mask);

    if (!digits) {
        return '';
    }

    let result = '';
    let used = 0;

    for (const char of mask) {
        if (char === '#') {
            if (used === digits.length) {
                break;
            }

            result += digits[used++];
        } else if (used < digits.length || result === '') {
            result += char;
        } else {
            break;
        }
    }

    return result;
}

export function initPhoneMask() {
    document.addEventListener('input', (event) => {
        const input = event.target;
        const mask = getMask();

        if (!mask || !(input instanceof HTMLInputElement) || !input.matches(SELECTOR)) {
            return;
        }

        // Deleting back into the literal prefix would re-add it; let the user clear it.
        if (event.inputType && event.inputType.startsWith('delete') && nationalDigits(input.value, mask) === '') {
            input.value = '';

            return;
        }

        input.value = formatPhone(input.value, mask);
    });

    document.addEventListener('focusin', (event) => {
        const input = event.target;
        const mask = getMask();

        if (mask && input instanceof HTMLInputElement && input.matches(SELECTOR)) {
            input.placeholder = mask.replace(/#/g, '0');
            input.setAttribute('inputmode', 'tel');
        }
    });
}
