/**
 * Standalone check for the design preset contract
 * (docs/developer/theme-api/presets.md, docs/adr/004-design-token-presets.md).
 *
 * No JS test runner in the repo yet (same shape as the PHP smoke tests
 * under tests/mintcart/ - plain script, exit 0 = pass, 1 = fail). Uses
 * only Node built-ins, no dependency beyond what the build already needs.
 *
 * A preset may only redeclare tokens that settings/tokens.css already
 * declares, plus its own `--mc-preset-<code>-*` intermediates. That rule is
 * what keeps presets orthogonal to component DOM: a preset that invents a
 * token name is introducing component-facing API no component reads, and a
 * preset that sets a component-local token (--mc-button-bg, ...) styles one
 * component out of step with the rest.
 *
 * Run: node tests/frontend/preset-tokens-test.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

const CSS_ROOT = path.join(__dirname, '..', '..', 'assets', 'src', 'css');
const TOKENS_FILE = path.join(CSS_ROOT, 'settings', 'tokens.css');
const PRESETS_DIR = path.join(CSS_ROOT, 'presets');

const failures = [];

function check(condition, message) {
    if (condition) {
        console.log(`  ok   ${message}`);
    } else {
        console.log(`  FAIL ${message}`);
        failures.push(message);
    }
}

/** Body of the first rule block whose selector matches, brace-matched. */
function ruleBody(css, selectorPattern) {
    const match = css.match(selectorPattern);

    if (!match) {
        return null;
    }

    const open = css.indexOf('{', match.index);
    let depth = 0;

    for (let i = open; i < css.length; i++) {
        if (css[i] === '{') {
            depth++;
        } else if (css[i] === '}') {
            depth--;

            if (depth === 0) {
                return css.slice(open + 1, i);
            }
        }
    }

    return null;
}

function declaredTokens(css) {
    return [...css.matchAll(/(--mc-[a-z0-9-]+)\s*:/g)].map((m) => m[1]);
}

/** Selectors in a stylesheet, ignoring comments and at-rule preludes. */
function selectors(css) {
    const withoutComments = css.replace(/\/\*[\s\S]*?\*\//g, '');

    return [...withoutComments.matchAll(/(^|})\s*([^{}@]+?)\s*{/g)]
        .map((m) => m[2].trim())
        .filter(Boolean);
}

const baseTokens = new Set(declaredTokens(ruleBody(fs.readFileSync(TOKENS_FILE, 'utf8'), /:root\s*{/)));

console.log(`settings/tokens.css declares ${baseTokens.size} tokens on :root`);

const presetFiles = fs.readdirSync(PRESETS_DIR).filter((f) => f.endsWith('.css')).sort();

check(presetFiles.length >= 2, `at least two presets ship (found ${presetFiles.length}: ${presetFiles.join(', ')})`);

for (const file of presetFiles) {
    const code = path.basename(file, '.css');
    const css = fs.readFileSync(path.join(PRESETS_DIR, file), 'utf8');

    console.log(`\npresets/${file}`);

    const expectedSelector = `:root[data-mc-preset='${code}']`;
    const found = selectors(css);

    check(
        found.length === 1 && found[0] === expectedSelector,
        `contains exactly one rule block, selector ${expectedSelector} (found: ${found.join(' | ') || 'none'})`
    );

    const body = ruleBody(css, new RegExp(`:root\\[data-mc-preset='${code}'\\]\\s*{`));

    check(body !== null, 'its rule block is parseable');

    if (body === null) {
        continue;
    }

    const tokens = declaredTokens(body);
    const localPrefix = `--mc-preset-${code}-`;
    const overrides = tokens.filter((t) => !t.startsWith(localPrefix));
    const unknown = overrides.filter((t) => !baseTokens.has(t));

    check(
        unknown.length === 0,
        `redeclares only tokens settings/tokens.css already declares (${overrides.length} overrides, ${tokens.length - overrides.length} preset-local; unknown: ${unknown.join(', ') || 'none'})`
    );
}

console.log('');

if (failures.length) {
    console.log(`FAILED (${failures.length})`);
    process.exit(1);
}

console.log('All preset contract checks passed.');
