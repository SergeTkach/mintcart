/**
 * Carrier checkout form (city and pickup point; Nova Poshta, Ukrposhta)
 * (catalog/view/theme/default/template/extension/shipping/mc_carrier_points.twig).
 * Vanilla JS, no dependencies. Two Select2-style pickers: a button that opens a
 * panel with a search box and a list. The city list comes from the carrier's
 * endpoint as you type (2+ letters); the points of the chosen city are loaded
 * once and filtered in the browser (by number or address).
 *
 * The checkout inserts the form markup (and runs this script) every time the
 * chosen delivery option changes, so initialisation is idempotent and the
 * listeners are delegated once. Choosing a city or a point fires `change` on the
 * hidden field, which makes the checkout save the form.
 */
(function () {
    'use strict';

    var HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };
    var MAX_SHOWN = 200;

    function getJson(url, params) {
        return fetch(url + '&' + new URLSearchParams(params), { headers: HEADERS }).then(function (r) {
            return r.json();
        });
    }

    function hostOf(picker) {
        return picker.closest('[data-mc-points]');
    }

    function pickerOf(root, name) {
        return root.querySelector('[data-mc-picker="' + name + '"]');
    }

    function refInput(root, name) {
        return root.querySelector(name === 'city' ? '[data-mc-points-city-ref]' : '[data-mc-points-point-ref]');
    }

    function setLabel(picker, text) {
        picker.querySelector('[data-mc-picker-label]').textContent = text;
    }

    function setEnabled(picker, enabled) {
        picker.querySelector('.mc-picker__toggle').disabled = !enabled;
    }

    function closeAll(except) {
        document.querySelectorAll('.mc-picker.is-open').forEach(function (picker) {
            if (picker !== except) {
                close(picker);
            }
        });
    }

    function close(picker) {
        picker.classList.remove('is-open');
        picker.querySelector('.mc-picker__panel').hidden = true;
        picker.querySelector('.mc-picker__toggle').setAttribute('aria-expanded', 'false');
    }

    function open(picker) {
        closeAll(picker);
        picker.classList.add('is-open');
        picker.querySelector('.mc-picker__panel').hidden = false;
        picker.querySelector('.mc-picker__toggle').setAttribute('aria-expanded', 'true');

        var search = picker.querySelector('.mc-picker__search');

        search.value = '';
        search.focus();
        render(picker, picker.mcItems || [], false);

        if (picker.hasAttribute('data-remote')) {
            message(picker, hostOf(picker).dataset.textTypeMore);
        }
    }

    function message(picker, text) {
        var list = picker.querySelector('.mc-picker__list');
        var li = document.createElement('li');

        li.className = 'mc-picker__empty';
        li.textContent = text;
        list.replaceChildren(li);
    }

    function render(picker, items, noneMessage) {
        var list = picker.querySelector('.mc-picker__list');

        if (!items.length) {
            if (noneMessage) {
                message(picker, hostOf(picker).dataset.textNone);
            } else {
                list.replaceChildren();
            }

            return;
        }

        var fragment = document.createDocumentFragment();
        var current = refInput(hostOf(picker), picker.dataset.mcPicker).value;

        items.slice(0, MAX_SHOWN).forEach(function (item) {
            var li = document.createElement('li');

            li.className = 'mc-picker__option';
            li.setAttribute('role', 'option');
            li.dataset.id = item.id;
            li.dataset.text = item.text;
            li.textContent = item.text;
            li.setAttribute('aria-selected', item.id === current ? 'true' : 'false');
            fragment.append(li);
        });

        list.replaceChildren(fragment);
    }

    function moveActive(picker, step) {
        var options = Array.prototype.slice.call(picker.querySelectorAll('.mc-picker__option'));

        if (!options.length) {
            return;
        }

        var index = options.findIndex(function (li) {
            return li.classList.contains('is-active');
        });

        options.forEach(function (li) {
            li.classList.remove('is-active');
        });

        index = (index + step + options.length) % options.length;
        options[index].classList.add('is-active');
        options[index].scrollIntoView({ block: 'nearest' });
    }

    function choose(picker, li) {
        var root = hostOf(picker);
        var name = picker.dataset.mcPicker;
        var ref = refInput(root, name);

        ref.value = li.dataset.id;
        setLabel(picker, li.dataset.text);
        close(picker);
        picker.querySelector('.mc-picker__toggle').focus();

        if (name === 'city') {
            clearPoint(root);
            loadPoints(root);
        }

        ref.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function clearPoint(root) {
        var picker = pickerOf(root, 'point');
        var ref = refInput(root, 'point');

        if (!picker) {
            return;
        }

        ref.value = '';
        picker.mcItems = [];
        setLabel(picker, root.dataset.textChoose);
        setEnabled(picker, false);
    }

    function loadPoints(root) {
        var picker = pickerOf(root, 'point');

        // No city picker: the point picker searches on its own (e.g. by postcode).
        if (!picker || !root.querySelector('[data-mc-points-city-ref]')) {
            return;
        }

        var cityRef = refInput(root, 'city').value;
        var selected = refInput(root, 'point').value;

        picker.mcItems = [];
        setEnabled(picker, false);

        if (!cityRef) {
            setLabel(picker, root.dataset.textChoose);
            return;
        }

        setLabel(picker, root.dataset.textLoading);

        getJson(root.dataset.pointsUrl, { city: cityRef, type: root.dataset.method }).then(function (json) {
            // The city may have changed while the list was loading.
            if (refInput(root, 'city').value !== cityRef) {
                return;
            }

            picker.mcItems = json.items;
            setEnabled(picker, true);

            var found = json.items.filter(function (item) {
                return item.id === selected;
            })[0];

            if (!found) {
                refInput(root, 'point').value = '';
            }

            setLabel(picker, found ? found.text : root.dataset.textChoose);
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

    if (!window.mcCarrierPoints) {
        var timer = null;

        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('.mc-picker__toggle');

            if (toggle) {
                var picker = toggle.closest('.mc-picker');

                if (picker.classList.contains('is-open')) {
                    close(picker);
                } else {
                    open(picker);
                }

                return;
            }

            var li = event.target.closest('.mc-picker__option');

            if (li) {
                choose(li.closest('.mc-picker'), li);
                return;
            }

            if (!event.target.closest('.mc-picker')) {
                closeAll(null);
            }
        });

        document.addEventListener('input', function (event) {
            var search = event.target.closest('.mc-picker__search');

            if (!search) {
                return;
            }

            var picker = search.closest('.mc-picker');
            var term = search.value.trim();

            if (picker.hasAttribute('data-remote')) {
                clearTimeout(timer);

                if (term.length < 2) {
                    message(picker, hostOf(picker).dataset.textTypeMore);
                    return;
                }

                timer = setTimeout(function () {
                    getJson(picker.dataset.searchUrl || hostOf(picker).dataset.citiesUrl, { term: term }).then(function (json) {
                        // Ignore an answer for text that has been replaced since.
                        if (search.value.trim() === term) {
                            picker.mcItems = json.items;
                            render(picker, json.items, true);
                        }
                    });
                }, 250);

                return;
            }

            var needle = term.toLowerCase();

            render(picker, (picker.mcItems || []).filter(function (item) {
                return item.text.toLowerCase().indexOf(needle) !== -1;
            }), true);
        });

        document.addEventListener('keydown', function (event) {
            var picker = event.target.closest('.mc-picker');

            if (!picker || !picker.classList.contains('is-open')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                close(picker);
                picker.querySelector('.mc-picker__toggle').focus();
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                moveActive(picker, event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Enter') {
                var active = picker.querySelector('.mc-picker__option.is-active');

                // Enter in the search box must never submit the checkout form.
                event.preventDefault();

                if (active) {
                    choose(picker, active);
                }
            }
        });

        window.mcCarrierPoints = { init: init };
    }

    init();
})();
