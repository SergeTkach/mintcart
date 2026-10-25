/**
 * Carrier checkout form (city search and pickup points; Nova Poshta, Ukrposhta)
 * (catalog/view/theme/default/template/extension/shipping/<carrier>.twig).
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
        var select = root.querySelector('[data-mc-points-point]');
        var cityRef = root.querySelector('[data-mc-points-city-ref]').value;

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
        document.querySelectorAll('[data-mc-points]').forEach(function (root) {
            if (!root.dataset.mcPointsReady) {
                root.dataset.mcPointsReady = '1';
                loadPoints(root);
            }
        });
    }

    function hideList(root) {
        var list = root.querySelector('[data-mc-points-cities]');

        list.hidden = true;
        root.querySelector('[data-mc-points-city]').setAttribute('aria-expanded', 'false');
    }

    if (!window.mcCarrierPoints) {
        var timer = null;

        document.addEventListener('input', function (event) {
            var input = event.target.closest('[data-mc-points-city]');

            if (!input) {
                return;
            }

            var root = input.closest('[data-mc-points]');
            var ref = root.querySelector('[data-mc-points-city-ref]');

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
                    var list = root.querySelector('[data-mc-points-cities]');

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
            var root = li.closest('[data-mc-points]');
            var ref = root.querySelector('[data-mc-points-city-ref]');

            root.querySelector('[data-mc-points-city]').value = li.dataset.text;
            ref.value = li.dataset.id;
            hideList(root);
            loadPoints(root);
            ref.dispatchEvent(new Event('change', { bubbles: true }));
        };

        document.addEventListener('click', function (event) {
            var li = event.target.closest('[data-mc-points-cities] [data-id]');

            if (li) {
                choose(li);
            }
        });

        document.addEventListener('keydown', function (event) {
            var li = event.target.closest('[data-mc-points-cities] [data-id]');

            if (li && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                choose(li);
            }
        });

        window.mcCarrierPoints = { init: init };
    }

    init();
})();
