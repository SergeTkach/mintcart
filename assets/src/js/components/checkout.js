/**
 * mc-checkout - the Mint single-page checkout UI
 * (`catalog/view/theme/mint_default/template/checkout/checkout.twig`).
 *
 * Boot: fetch `mintcart/checkout/state`, fill the form, render delivery/
 * payment options, items and totals. Every `change` inside the form is
 * debounced and posted to `mintcart/checkout/update`, which persists it in
 * the stock OpenCart checkout session and returns recalculated options and
 * totals (a newer edit aborts the stale request). Submit posts to
 * `mintcart/checkout/confirm`: field errors are shown inline; on success
 * the chosen payment extension's own confirm markup is injected below the
 * summary - that markup is legacy OpenCart (jQuery, its own
 * `#button-confirm`), so its inline <script> tags are re-created to run.
 *
 * Values typed by the customer are only ever written with `.value`.
 * Method titles, product names and totals come from the store's own
 * catalog/extension data, which OpenCart stores HTML-encoded and the stock
 * templates print unescaped - they are inserted the same way here.
 *
 * Contract: docs/developer/commerce/checkout-service.md
 */

import {
    fetchCheckoutState,
    updateCheckout,
    confirmCheckout,
    applyCheckoutCode,
    removeCheckoutCode,
} from '../commerce/checkout-client.js';
import { Modal } from 'bootstrap';

const UPDATE_DELAY = 300;

function option(value, label, selected) {
    const el = document.createElement('option');

    el.value = String(value);
    el.textContent = label;
    el.selected = String(value) === String(selected);

    return el;
}

// Legacy payment-extension markup relies on inline scripts; scripts
// inserted through innerHTML never execute, so swap in fresh copies.
function runScripts(container) {
    container.querySelectorAll('script').forEach((old) => {
        const script = document.createElement('script');

        [...old.attributes].forEach((attr) => script.setAttribute(attr.name, attr.value));
        script.textContent = old.textContent;
        old.replaceWith(script);
    });
}

function initCheckoutRoot(root) {
    const form = root.querySelector('[data-mc-checkout-form]');
    const submit = root.querySelector('[data-mc-checkout-submit]');
    const message = root.querySelector('[data-mc-checkout-message]');
    const payment = root.querySelector('[data-mc-checkout-payment]');
    const text = root.dataset;

    const countrySelect = form.elements.country_id;
    const zoneSelect = form.elements.zone_id;

    let countries = [];
    let zonesCountry = null;
    let timer = null;
    let controller = null;

    function showMessage(html) {
        message.querySelector('.mc-alert__message').innerHTML = html;
        message.hidden = false;
    }

    function clearErrors() {
        message.hidden = true;

        root.querySelectorAll('.has-error').forEach((el) => el.classList.remove('has-error'));
        root.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
        root.querySelectorAll('.mc-field__error').forEach((el) => {
            el.hidden = true;
            el.textContent = '';
        });
    }

    function showErrors(errors) {
        let first = null;
        const general = [];

        Object.entries(errors).forEach(([name, msg]) => {
            const field = root.querySelector(`[data-mc-checkout-field="${name}"]`);
            const error = field
                ? field.querySelector('.mc-field__error')
                : root.querySelector(`[data-mc-checkout-error="${name}"]`);

            if (!error) {
                general.push(msg);
                return;
            }

            error.innerHTML = msg;
            error.hidden = false;

            if (field) {
                field.classList.add('has-error');
                field.querySelector('.mc-field__control').setAttribute('aria-invalid', 'true');
                first = first || field.querySelector('.mc-field__control');
            }
        });

        if (general.length) {
            showMessage(general.join('<br>'));
        }

        first?.focus();
    }

    function renderCountries(selected) {
        countrySelect.replaceChildren(option('', text.mcTextSelect, selected));
        countries.forEach((c) => countrySelect.append(option(c.country_id, c.name, selected)));
    }

    function renderZones(zones, selected) {
        if (zones.length) {
            zoneSelect.replaceChildren(option('', text.mcTextSelect, selected));
            zones.forEach((z) => zoneSelect.append(option(z.zone_id, z.name, selected)));
        } else {
            zoneSelect.replaceChildren(option(0, text.mcTextNone, 0));
        }
    }

    function renderPostcode(countryId) {
        const country = countries.find((c) => String(c.country_id) === String(countryId));

        root.querySelector('[data-mc-checkout-postcode]').hidden = !(country && country.postcode_required);
    }

    function renderOptions(container, name, groups, selected, emptyText) {
        if (!container) {
            return;
        }

        container.replaceChildren();

        if (!groups.length) {
            const empty = document.createElement('p');

            empty.className = 'mc-checkout__empty';
            empty.textContent = emptyText;
            container.append(empty);
            return;
        }

        groups.forEach((group) => {
            const wrap = document.createElement('div');

            wrap.className = 'mc-checkout__option-group';

            if (group.title && group.options.length > 1) {
                const title = document.createElement('p');

                title.className = 'mc-checkout__option-group-title';
                title.innerHTML = group.title;
                wrap.append(title);
            }

            group.options.forEach((item) => {
                const label = document.createElement('label');

                label.className = 'mc-radio mc-checkout__option';
                label.innerHTML = `
                    <input type="radio" class="mc-radio__input" name="${name}">
                    <span class="mc-radio__label mc-checkout__option-title"></span>
                    <span class="mc-checkout__option-price"></span>
                `;

                const input = label.querySelector('input');

                input.value = item.id;
                input.checked = item.id === selected;
                label.querySelector('.mc-checkout__option-title').innerHTML = item.title;
                label.querySelector('.mc-checkout__option-price').innerHTML = item.text || '';

                wrap.append(label);
            });

            container.append(wrap);
        });
    }

    function renderSummary(state) {
        const items = root.querySelector('[data-mc-checkout-items]');

        items.replaceChildren(...state.items.map((item) => {
            const li = document.createElement('li');
            const options = (item.option_summary || []).map((o) => `${o.name}: ${o.value}`).join(', ');

            li.className = 'mc-checkout__item';
            li.innerHTML = `
                <span class="mc-checkout__item-name"></span>
                <span class="mc-checkout__item-total"></span>
                <span class="mc-checkout__item-meta"></span>
            `;
            li.querySelector('.mc-checkout__item-name').innerHTML = item.name;
            li.querySelector('.mc-checkout__item-total').innerHTML = item.total;
            li.querySelector('.mc-checkout__item-meta').innerHTML = `× ${item.quantity}${options ? ` · ${options}` : ''}`;

            return li;
        }));

        const totals = root.querySelector('[data-mc-checkout-totals]');

        totals.replaceChildren();
        state.totals.lines.forEach((line) => {
            const row = document.createElement('div');

            row.className = 'mc-checkout__total';
            row.classList.toggle('mc-checkout__total--grand', line.code === 'total');
            row.innerHTML = '<dt></dt><dd></dd>';
            row.querySelector('dt').innerHTML = line.title;
            row.querySelector('dd').innerHTML = line.text;
            totals.append(row);
        });
    }

    const codeForm = root.querySelector('[data-mc-checkout-code]');
    const codeError = codeForm.querySelector('[data-mc-checkout-code-error]');
    const codeList = codeForm.querySelector('[data-mc-checkout-codes]');

    function renderCodes(codes) {
        codeForm.hidden = !codes.enabled;

        codeList.replaceChildren(...codes.applied.map(({ type, code }) => {
            const li = document.createElement('li');
            const label = document.createElement('span');
            const remove = document.createElement('button');

            li.className = 'mc-checkout__code-chip';
            label.textContent = code;
            remove.type = 'button';
            remove.className = 'mc-checkout__code-remove';
            remove.dataset.mcAction = 'remove-checkout-code';
            remove.dataset.mcCodeType = type;
            remove.setAttribute('aria-label', codeList.dataset.mcTextRemove.replace('%s', code));
            remove.textContent = '×';
            li.append(label, remove);

            return li;
        }));
    }

    function render(state, full) {
        if (full) {
            countries = state.countries;

            Object.entries(state.contact).forEach(([key, value]) => {
                if (form.elements[key]) {
                    form.elements[key].value = value;
                    form.elements[key].readOnly = state.logged;
                }
            });

            ['city', 'address_1', 'postcode'].forEach((key) => {
                form.elements[key].value = state.address[key];
            });

            form.elements.comment.value = state.comment;
            renderCountries(state.address.country_id);

            const loginEl = root.querySelector('[data-mc-checkout-login]');

            loginEl.innerHTML = state.login_html;
            loginEl.hidden = !state.login_html;

            const agree = root.querySelector('[data-mc-checkout-agree]');

            agree.hidden = !state.agree_html;
            agree.querySelector('[data-mc-checkout-agree-text]').innerHTML = state.agree_html;
            agree.querySelectorAll('a').forEach((a) => {
                a.target = '_blank';
                a.rel = 'noopener';

                if (state.agree_url) {
                    // `agree` is the hook of common.js's legacy jQuery dialog;
                    // drop it so only the Mint dialog opens.
                    a.classList.remove('agree');
                    a.dataset.mcAction = 'open-checkout-terms';
                    a.dataset.mcTermsUrl = state.agree_url;
                }
            });
        }

        if (zonesCountry !== String(state.address.country_id)) {
            zonesCountry = String(state.address.country_id);
            renderZones(state.zones, state.address.zone_id);
        }

        renderPostcode(state.address.country_id);

        renderOptions(
            root.querySelector('[data-mc-checkout-shipping]'),
            'shipping_method',
            state.shipping.methods.map((m) => ({ title: m.method, options: m.options })),
            state.shipping.selected,
            text.mcTextNoShipping
        );

        renderOptions(
            root.querySelector('[data-mc-checkout-payment-methods]'),
            'payment_method',
            state.payment.methods.length
                ? [{ title: '', options: state.payment.methods.map((m) => ({ id: m.code, title: m.title, text: m.terms })) }]
                : [],
            state.payment.selected,
            text.mcTextNoPayment
        );

        renderSummary(state);
        renderCodes(state.codes);
    }

    // A code changes the total: an order already created for the old total
    // must be confirmed again, so drop the payment step.
    async function sendCode(request) {
        codeError.hidden = true;
        codeForm.classList.add('is-loading');
        resetPayment();

        try {
            const json = await request();

            if (json.redirect) {
                window.location.href = json.redirect;
                return;
            }

            render(json.state, false);
            document.dispatchEvent(new CustomEvent('mc:checkout:updated', { bubbles: true, detail: json.state }));

            if (json.error) {
                codeError.textContent = json.error;
                codeError.hidden = false;
            } else {
                codeForm.elements.code.value = '';
            }
        } catch {
            showMessage(text.mcTextError);
        } finally {
            codeForm.classList.remove('is-loading');
        }
    }

    const terms = root.querySelector('[data-mc-checkout-terms]');

    root.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-mc-action="open-checkout-terms"]');

        if (!link || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        event.preventDefault();

        try {
            const response = await fetch(link.dataset.mcTermsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            // The store's own information page, printed unescaped by stock
            // OpenCart too.
            terms.querySelector('[data-mc-checkout-terms-body]').innerHTML = await response.text();
            terms.querySelector('[data-mc-checkout-terms-title]').textContent = link.textContent.trim();
            Modal.getOrCreateInstance(terms).show();
        } catch {
            window.open(link.href, '_blank', 'noopener');
        }
    });

    codeForm.addEventListener('submit', (event) => {
        event.preventDefault();
        sendCode(() => applyCheckoutCode(codeForm.elements.code.value));
    });

    codeList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-mc-action="remove-checkout-code"]');

        if (button) {
            sendCode(() => removeCheckoutCode(button.dataset.mcCodeType));
        }
    });

    function setGuestBlocked() {
        showMessage(root.querySelector('[data-mc-checkout-login]').innerHTML || text.mcTextLoginRequired);
        form.querySelectorAll('input, select, textarea').forEach((el) => {
            el.disabled = true;
        });
    }

    function resetPayment() {
        payment.hidden = true;
        payment.querySelector('[data-mc-checkout-payment-body]').replaceChildren();
        submit.hidden = false;
    }

    async function sendUpdate() {
        controller?.abort();
        controller = new AbortController();

        root.setAttribute('aria-busy', 'true');
        submit.disabled = true;

        try {
            const state = await updateCheckout(new FormData(form), controller.signal);

            if (state.redirect) {
                window.location.href = state.redirect;
                return;
            }

            render(state, false);
        } catch (error) {
            if (error.name !== 'AbortError') {
                showMessage(text.mcTextError);
            }

            return;
        } finally {
            root.setAttribute('aria-busy', 'false');
        }

        submit.disabled = false;
    }

    function scheduleUpdate(delay = UPDATE_DELAY) {
        clearTimeout(timer);
        timer = setTimeout(sendUpdate, delay);
    }

    form.addEventListener('change', (event) => {
        // A new country invalidates the region; the response brings its zones.
        if (event.target === countrySelect) {
            zoneSelect.value = '';
        }

        resetPayment();
        scheduleUpdate();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearTimeout(timer);
        controller?.abort();
        clearErrors();
        resetPayment();

        submit.disabled = true;
        submit.classList.add('is-loading');

        try {
            const json = await confirmCheckout(new FormData(form));

            if (json.redirect) {
                window.location.href = json.redirect;
                return;
            }

            if (json.errors) {
                if (json.state) {
                    render(json.state, false);
                }

                showErrors(json.errors);
                return;
            }

            const body = payment.querySelector('[data-mc-checkout-payment-body]');

            body.innerHTML = json.payment_html;
            runScripts(body);
            submit.hidden = true;
            payment.hidden = false;
            payment.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch {
            showMessage(text.mcTextError);
        } finally {
            submit.disabled = false;
            submit.classList.remove('is-loading');
        }
    });

    fetchCheckoutState()
        .then((state) => {
            if (state.redirect) {
                window.location.href = state.redirect;
                return;
            }

            render(state, true);
            root.setAttribute('aria-busy', 'false');

            if (!state.logged && !state.can_guest) {
                setGuestBlocked();
                return;
            }

            submit.disabled = false;

            // First visit: persist the prefilled defaults so delivery and
            // payment options get quoted straight away.
            if (!state.initialized) {
                scheduleUpdate(0);
            }
        })
        .catch(() => showMessage(text.mcTextError));
}

export function initCheckout(root = document) {
    root.querySelectorAll('[data-mc-checkout]').forEach(initCheckoutRoot);
}
