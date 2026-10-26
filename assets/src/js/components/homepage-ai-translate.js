/**
 * "AI translation" panel of the Homepage block form: all the translatable texts of the block
 * as one JSON (per language), to copy into an AI assistant and paste back translated.
 * Same idea as the st_* modules' stAiTranslate - the JSON is only for translation, the form
 * itself is edited through the normal fields.
 *
 * Markup: `<div data-mc-ai-translate data-languages='[{"id":2,"code":"uk-ua","name":"..."}]'
 * data-text-*="...">` inside the form `[data-mc-items-form]`.
 *
 * JSON shape: { "<lang code>": { "_name": "...", "<field>": "...", "<list>": [ { "<field>": "..." } ] } }
 * Text inputs are `block_content[<lang id>][<field>]` (flat) and
 * `block_content[<lang id>][<list>][<row>][<field>]` (rows of a `[data-mc-items]` list, in DOM order).
 */

function fieldsOf(form, languageId) {
    const flat = {};
    const lists = {};
    const prefix = 'block_content[' + languageId + ']';

    form.querySelectorAll('[name^="' + prefix + '["]').forEach((input) => {
        if (input.closest('template')) {
            return;
        }

        const parts = input.name.slice(prefix.length).match(/\[([^\]]+)\]/g).map((part) => part.slice(1, -1));

        if (parts.length === 1) {
            flat[parts[0]] = input;
        }
    });

    form.querySelectorAll('[data-mc-items]').forEach((root) => {
        const list = root.dataset.list;
        const rows = Array.from(root.querySelectorAll('[data-mc-items-row]'));

        lists[list] = rows.map((row) => {
            const fields = {};

            row.querySelectorAll('[name^="' + prefix + '[' + list + ']["]').forEach((input) => {
                const match = input.name.match(/\]\[([^\]]+)\]$/);

                if (match) {
                    fields[match[1]] = input;
                }
            });

            return fields;
        });
    });

    return { flat, lists };
}

function collect(form, languages) {
    const data = {};

    languages.forEach((language) => {
        const { flat, lists } = fieldsOf(form, language.id);
        const block = { _name: language.name };

        Object.keys(flat).forEach((key) => {
            block[key] = flat[key].value;
        });

        Object.keys(lists).forEach((list) => {
            if (!lists[list].length) {
                return;
            }

            block[list] = lists[list].map((fields) => {
                const item = {};

                Object.keys(fields).forEach((key) => {
                    item[key] = fields[key].value;
                });

                return item;
            });
        });

        data[language.code] = block;
    });

    return JSON.stringify(data, null, 2);
}

function setValue(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function apply(form, languages, json, texts) {
    let data;

    try {
        data = JSON.parse(json);
    } catch {
        window.alert(texts.invalid);

        return;
    }

    const notes = [];

    languages.forEach((language) => {
        const source = data[language.code];

        if (!source || typeof source !== 'object') {
            return;
        }

        const { flat, lists } = fieldsOf(form, language.id);

        Object.keys(flat).forEach((key) => {
            if (typeof source[key] === 'string') {
                setValue(flat[key], source[key]);
            }
        });

        Object.keys(lists).forEach((list) => {
            const items = source[list];

            if (!Array.isArray(items)) {
                return;
            }

            if (items.length !== lists[list].length) {
                notes.push(language.name + ' / ' + list + ': ' + items.length + ' → ' + lists[list].length);
            }

            lists[list].forEach((fields, index) => {
                const item = items[index];

                if (!item || typeof item !== 'object') {
                    return;
                }

                Object.keys(fields).forEach((key) => {
                    if (typeof item[key] === 'string') {
                        setValue(fields[key], item[key]);
                    }
                });
            });
        });
    });

    if (notes.length) {
        window.alert(texts.notes + '\n' + notes.join('\n'));
    }
}

export function initHomepageAiTranslate() {
    const root = document.querySelector('[data-mc-ai-translate]');
    const form = document.querySelector('[data-mc-items-form]');

    if (!root || !form) {
        return;
    }

    let languages = [];

    try {
        languages = JSON.parse(root.dataset.languages || '[]');
    } catch {
        languages = [];
    }

    if (languages.length < 2) {
        root.hidden = true;

        return;
    }

    const texts = {
        invalid: root.dataset.textInvalid,
        confirm: root.dataset.textConfirm,
        notes: root.dataset.textNotes,
    };

    const area = root.querySelector('[data-mc-ai-json]');
    let timer = null;

    const refresh = () => {
        area.value = collect(form, languages);
    };

    refresh();

    // Keep the JSON in step with the fields (debounced), rows added / removed included.
    form.addEventListener('input', (event) => {
        if (root.contains(event.target)) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(refresh, 500);
    });

    form.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-mc-items-add], [data-mc-items-remove]')) {
            clearTimeout(timer);
            timer = setTimeout(refresh, 100);
        }
    });

    root.querySelector('[data-mc-ai-refresh]').addEventListener('click', refresh);

    root.querySelector('[data-mc-ai-copy]').addEventListener('click', () => {
        area.select();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(area.value).catch(() => document.execCommand('copy'));
        } else {
            document.execCommand('copy');
        }
    });

    root.querySelector('[data-mc-ai-apply]').addEventListener('click', () => {
        if (window.confirm(texts.confirm)) {
            apply(form, languages, area.value, texts);
        }
    });
}
