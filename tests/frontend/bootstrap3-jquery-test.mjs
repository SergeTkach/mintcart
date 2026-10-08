/**
 * Standalone check for assets/src/js/compatibility/bootstrap3-jquery.js
 * (ADR 010). Same shape as preset-tokens-test.js: plain Node script, no DOM
 * library - tiny hand-written fakes stand in for elements and jQuery.
 *
 * Run: node tests/frontend/bootstrap3-jquery-test.mjs
 */

import {
    mirrorBootstrap3Attributes,
    createLegacyButtonPlugin,
    upgradeBootstrap3Classes,
} from '../../assets/src/js/compatibility/bootstrap3-jquery.js';

const failures = [];

function check(condition, message) {
    if (!condition) {
        failures.push(message);
    }
}

function fakeElement(attributes = {}, children = [], tag = 'button') {
    const attrs = new Map(Object.entries(attributes));

    return {
        nodeType: 1,
        tag,
        attrs,
        getAttribute: (name) => (attrs.has(name) ? attrs.get(name) : null),
        hasAttribute: (name) => attrs.has(name),
        setAttribute: (name, value) => attrs.set(name, String(value)),
        removeAttribute: (name) => attrs.delete(name),
        matches: (selector) => selector.split(',').some((part) => attrs.has(part.slice(1, -1))),
        querySelectorAll: (selector) => children.filter((child) => child.matches(selector)),
    };
}

// --- data-* mirroring -------------------------------------------------------

const collapse = fakeElement({ 'data-toggle': 'collapse', 'data-parent': '#accordion' });
const dismiss = fakeElement({ 'data-dismiss': 'alert' });
const alreadyBs = fakeElement({ 'data-toggle': 'tab', 'data-bs-toggle': 'pill' });
const plain = fakeElement({ class: 'btn' });
const root = fakeElement({ 'data-target': '#modal' }, [collapse, dismiss, alreadyBs, plain], 'div');

mirrorBootstrap3Attributes(root);

check(collapse.getAttribute('data-bs-toggle') === 'collapse', 'data-toggle mirrored to data-bs-toggle');
check(collapse.getAttribute('data-bs-parent') === '#accordion', 'data-parent mirrored');
check(dismiss.getAttribute('data-bs-dismiss') === 'alert', 'data-dismiss mirrored');
check(alreadyBs.getAttribute('data-bs-toggle') === 'pill', 'existing data-bs-* value wins');
check(!plain.hasAttribute('data-bs-toggle'), 'unrelated element untouched');
check(root.getAttribute('data-bs-target') === '#modal', 'root element itself mirrored');

// --- $.fn.button('loading' | 'reset') --------------------------------------

function fakeJQuery() {
    function wrap(elements) {
        const api = {
            each(fn) {
                elements.forEach((el, i) => fn.call(el, i, el));
                return api;
            },
            is: (tag) => elements[0].tag === tag,
            data(key, value) {
                const el = elements[0];
                el.store = el.store || {};

                if (value === undefined) {
                    if (key in el.store) {
                        return el.store[key];
                    }
                    const attr = 'data-' + key.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
                    return el.getAttribute(attr) ?? undefined;
                }
                el.store[key] = value;
                return api;
            },
            html(value) {
                if (value === undefined) {
                    return elements[0].content;
                }
                elements[0].content = value;
                return api;
            },
            val(value) {
                if (value === undefined) {
                    return elements[0].value;
                }
                elements[0].value = value;
                return api;
            },
            addClass(c) { elements[0].classes = (elements[0].classes || new Set()).add(c); return api; },
            removeClass(c) { elements[0].classes?.delete(c); return api; },
            attr(name, v) { elements[0].setAttribute(name, v); return api; },
            removeAttr(name) { elements[0].removeAttribute(name); return api; },
            prop(name, v) { elements[0][name] = v; return api; },
        };
        return api;
    }

    const $ = (el) => wrap([el]);
    $.wrap = (els) => wrap(els);
    return $;
}

const $ = fakeJQuery();
let fallbackCalledWith = null;
const plugin = createLegacyButtonPlugin($, function (arg) { fallbackCalledWith = arg; return this; });

const button = fakeElement({ 'data-loading-text': 'Loading...' });
button.content = 'Confirm Order';

plugin.call($.wrap([button]), 'loading');
check(button.content === 'Loading...', 'loading swaps in data-loading-text');
check(button.disabled === true && button.hasAttribute('disabled'), 'loading disables the button');
check(button.classes.has('disabled'), 'loading adds .disabled');

plugin.call($.wrap([button]), 'reset');
check(button.content === 'Confirm Order', 'reset restores the original text');
check(button.disabled === false && !button.hasAttribute('disabled'), 'reset re-enables the button');
check(!button.classes.has('disabled'), 'reset removes .disabled');

const input = fakeElement({ 'data-loading-text': 'Wait' }, [], 'input');
input.value = 'Continue';
plugin.call($.wrap([input]), 'loading');
check(input.value === 'Wait', 'input buttons use val()');
plugin.call($.wrap([input]), 'reset');
check(input.value === 'Continue', 'input reset restores val()');

const noText = fakeElement();
noText.content = 'Add';
plugin.call($.wrap([noText]), 'loading');
check(noText.content === 'Add', 'loading without data-loading-text keeps the text');

plugin.call($.wrap([button]), 'toggle');
check(fallbackCalledWith === 'toggle', "'toggle' is delegated to Bootstrap 5's plugin");

// --- .in -> .show, BS3 tab li.active ---------------------------------------

function classed(classes, attributes = {}, children = []) {
    const element = fakeElement(attributes, children, 'div');
    element.classList = new Set(classes.split(' ').filter(Boolean));
    element.classList.contains = element.classList.has;
    element.classList.remove = element.classList.delete;
    element.querySelectorAll = () => children.filter((child) => child.wanted);
    return element;
}

const openCollapse = classed('panel-collapse collapse in');
openCollapse.wanted = true;
const tabLi = classed('active');
const tabLink = classed('', { 'data-toggle': 'tab' });
tabLink.parentElement = tabLi;
tabLink.wanted = true;
const notCollapse = classed('in');
const scope = classed('', {}, [openCollapse, tabLink, notCollapse]);

upgradeBootstrap3Classes(scope);

check(openCollapse.classList.has('show') && !openCollapse.classList.has('in'), '.collapse.in becomes .collapse.show');
check(tabLink.classList.has('active'), 'BS3 tab: link becomes .active');
check(!tabLi.classList.has('active'), 'BS3 tab: li loses .active');
check(notCollapse.classList.has('in'), '.in without .collapse/.fade is left alone');

if (failures.length) {
    console.error('FAIL bootstrap3-jquery:\n - ' + failures.join('\n - '));
    process.exit(1);
}

console.log('OK bootstrap3-jquery: all checks passed');
