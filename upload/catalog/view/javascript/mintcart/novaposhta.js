/**
 * Nova Poshta checkout form: city search and pickup points
 * (catalog/view/theme/default/template/extension/shipping/mc_novaposhta.twig).
 * Vanilla JS, no dependencies. The checkout inserts the form markup (and
 * runs this script) every time the chosen delivery option changes, so
 * initialisation is idempotent and the listeners are delegated once.
 * Changing the city or the point fires `change` on the field, which makes
 * the checkout save the form.
 */
(function () {
    'use strict';

    var HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

    function getJson(url, params) {
        return fetch(url + '&' + new URLSearchParams(params), { headers: HEADERS }).then(function (r) {
            return r.json();
        });
    }

    function loadPoints(root) {
        var select = root.querySelector('[data-mc-np-point]');
        var cityRef = root.querySelector('[data-mc-np-city-ref]').value;

        if (!select) {
            return;
        }

        var selected = select.dataset.selected || '';

        select.innerHTML = '';
        select.disabled = true;

        if (!cityRef) {
            select.append(new Option(root.dataset.textChoose, ''));
            return;
        }

        select.append(new Option(root.dataset.textLoading, ''));

        getJson(root.dataset.pointsUrl, { city: cityRef, type: root.dataset.method }).then(function (json) {
            var fragment = document.createDocumentFragment();

            fragment.append(new Option(root.dataset.textChoose, ''));
            json.items.forEach(function (item) {
                fragment.append(new Option(item.text, item.id, false, item.id === selected));
            });

            select.replaceChildren(fragment);
            select.dataset.selected = '';
            select.disabled = false;
        });
    }

    function init() {
        document.querySelectorAll('[data-mc-np]').forEach(function (root) {
            if (!root.dataset.mcNpReady) {
                root.dataset.mcNpReady = '1';
                loadPoints(root);
            }
        });
    }

    function hideList(root) {
        var list = root.querySelector('[data-mc-np-cities]');

        list.hidden = true;
        root.querySelector('[data-mc-np-city]').setAttribute('aria-expanded', 'false');
    }

    if (!window.mcNovaPoshta) {
        var timer = null;

        document.addEventListener('input', function (event) {
            var input = event.target.closest('[data-mc-np-city]');

            if (!input) {
                return;
            }

            var root = input.closest('[data-mc-np]');
            var ref = root.querySelector('[data-mc-np-city-ref]');

            // Typing again drops the previous choice until a city is picked.
            if (ref.value) {
                ref.value = '';
                loadPoints(root);
                ref.dispatchEvent(new Event('change', { bubbles: true }));
            }

            clearTimeout(timer);

            if (input.value.trim().length < 2) {
                hideList(root);
                return;
            }

            timer = setTimeout(function () {
                getJson(root.dataset.citiesUrl, { term: input.value.trim() }).then(function (json) {
                    var list = root.querySelector('[data-mc-np-cities]');

                    list.replaceChildren();

                    if (!json.items.length) {
                        var empty = document.createElement('li');

                        empty.className = 'list-group-item text-muted';
                        empty.textContent = root.dataset.textNone;
                        list.append(empty);
                    }

                    json.items.forEach(function (item) {
                        var li = document.createElement('li');

                        li.className = 'list-group-item list-group-item-action';
                        li.setAttribute('role', 'option');
                        li.tabIndex = 0;
                        li.dataset.id = item.id;
                        li.dataset.text = item.text;
                        li.textContent = item.text;
                        list.append(li);
                    });

                    list.hidden = false;
                    input.setAttribute('aria-expanded', 'true');
                });
            }, 250);
        });

        var choose = function (li) {
            var root = li.closest('[data-mc-np]');
            var ref = root.querySelector('[data-mc-np-city-ref]');

            root.querySelector('[data-mc-np-city]').value = li.dataset.text;
            ref.value = li.dataset.id;
            hideList(root);
            loadPoints(root);
            ref.dispatchEvent(new Event('change', { bubbles: true }));
        };

        document.addEventListener('click', function (event) {
            var li = event.target.closest('[data-mc-np-cities] [data-id]');

            if (li) {
                choose(li);
            }
        });

        document.addEventListener('keydown', function (event) {
            var li = event.target.closest('[data-mc-np-cities] [data-id]');

            if (li && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                choose(li);
            }
        });

        window.mcNovaPoshta = { init: init };
    }

    init();
})();
