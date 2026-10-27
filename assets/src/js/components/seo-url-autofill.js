/**
 * SEO URL auto-fill in the product, category, information page and brand forms.
 *
 * While the name / title is typed, an EMPTY "SEO URL" field of that language is filled with the
 * keyword the server makes from it (`SEO -> URL Generator`: the template, transliterated, unique in
 * the store: `mintcart/seo/url_generator/slug`). A keyword that was already there, or that the user
 * typed, is never touched; the one this script filled keeps following the name until the user edits
 * it. Loaded on those forms by an event (migration 202610251400), no template is patched.
 *
 * Field names (stock OpenCart 3): product_description[<lang>][name] + product_seo_url[<store>][<lang>],
 * category_description[..][name] + category_seo_url, information_description[..][title] +
 * information_seo_url, manufacturer `name` + manufacturer_seo_url.
 */

const KINDS = [
    {
        type: 'product',
        seo: 'product_seo_url',
        name: (language) => `product_description[${language}][name]`,
        idParam: 'product_id',
        extra: (form) => ({
            brand_id: form.querySelector('[name="manufacturer_id"]')?.value || '',
            category_id: form.querySelector('[name="product_category[]"]')?.value || '',
            model: form.querySelector('[name="model"]')?.value || '',
        }),
        watch: ['manufacturer_id', 'product_category[]', 'model'],
    },
    {
        type: 'category',
        seo: 'category_seo_url',
        name: (language) => `category_description[${language}][name]`,
        idParam: 'category_id',
        extra: (form) => ({ parent_id: form.querySelector('[name="parent_id"]')?.value || '' }),
        watch: ['parent_id'],
    },
    {
        type: 'information',
        seo: 'information_seo_url',
        name: (language) => `information_description[${language}][title]`,
        idParam: 'information_id',
        extra: () => ({}),
        watch: [],
    },
    {
        type: 'manufacturer',
        seo: 'manufacturer_seo_url',
        name: () => 'name',
        idParam: 'manufacturer_id',
        extra: () => ({}),
        watch: [],
    },
];

function seoFields(form, kind) {
    return Array.from(form.querySelectorAll(`input[name^="${kind.seo}["]`)).map((input) => {
        const match = input.name.match(/\[(\d+)\]\[(\d+)\]$/);

        return match ? { input, store: match[1], language: match[2] } : null;
    }).filter(Boolean);
}

export function initSeoUrlAutofill() {
    const token = new URLSearchParams(window.location.search).get('user_token');

    if (!token) {
        return;
    }

    const query = new URLSearchParams(window.location.search);

    KINDS.forEach((kind) => {
        const fields = seoFields(document, kind);

        if (!fields.length) {
            return;
        }

        const url = `index.php?route=mintcart/seo/url_generator/slug&user_token=${encodeURIComponent(token)}`;
        const timers = {};

        // A field the user types in is theirs from now on.
        fields.forEach(({ input }) => {
            input.addEventListener('input', () => {
                input.dataset.mcAuto = '0';
            });
        });

        function fill(language) {
            const nameInput = document.querySelector(`[name="${CSS.escape(kind.name(language))}"]`);
            const name = nameInput ? nameInput.value.trim() : '';

            if (name === '') {
                return;
            }

            fields.filter((field) => field.language === language).forEach((field) => {
                const { input, store } = field;

                if (input.value !== '' && input.dataset.mcAuto !== '1') {
                    return;
                }

                const params = new URLSearchParams({
                    type: kind.type,
                    language_id: language,
                    store_id: store,
                    name,
                    ignore_id: query.get(kind.idParam) || '0',
                    ...kind.extra(document),
                });

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                })
                    .then((response) => response.json())
                    .then((json) => {
                        // Typed in the meantime: leave it.
                        if (json.slug && (input.value === '' || input.dataset.mcAuto === '1')) {
                            input.value = json.slug;
                            input.dataset.mcAuto = '1';
                        }
                    })
                    .catch(() => {});
            });
        }

        function schedule(language) {
            clearTimeout(timers[language]);
            timers[language] = setTimeout(() => fill(language), 450);
        }

        const languages = Array.from(new Set(fields.map((field) => field.language)));

        languages.forEach((language) => {
            const nameInput = document.querySelector(`[name="${CSS.escape(kind.name(language))}"]`);

            if (nameInput) {
                nameInput.addEventListener('input', () => schedule(language));
            }
        });

        // A brand / category / model that is part of the template changed.
        kind.watch.forEach((name) => {
            document.addEventListener('change', (event) => {
                if (event.target instanceof HTMLElement && event.target.getAttribute('name') === name) {
                    languages.forEach(schedule);
                }
            });
        });
    });
}
