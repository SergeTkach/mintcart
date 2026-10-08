#!/usr/bin/env node
/**
 * MintCart release packaging script.
 *
 * docs/implementation-log.md's "Phase 11 (Technical Plan) — Update /
 * Compatibility" section flagged this as a standing TODO: `npm install` and
 * `npm run build`, plus the PHP vendor libraries already committed under
 * `upload/system/**\/vendor/`, are dev/release-time tooling only — the
 * person *installing* MintCart must never need Node or Composer. This
 * script produces the installable artifact so a maintainer (or CI) can do
 * that work once, the same way OpenCart itself ships a pre-built `upload/`
 * download.
 *
 * What it does:
 *   1. Runs `npm run build` (fresh minified assets/dist/{css,js}).
 *   2. Packages the *committed* (`git archive HEAD`) contents of `upload/`
 *      into a staging directory — this deliberately excludes anything
 *      .gitignore already excludes from a real install (`config.php`,
 *      `.htaccess`, `install.lock`, `storage/*` contents, ...), matching
 *      how OpenCart's own downloadable zip ships a clean `upload/` an
 *      installer still has to run against.
 *   3. Copies the freshly built `assets/dist/{css,js}` into
 *      `upload/assets/dist/{css,js}` inside that staging copy — mirroring the
 *      local-dev convention already documented in `.gitignore` (`assets/`
 *      lives outside `upload/`, which is what the webserver serves, so the
 *      built output needs to land inside `upload/` to be reachable by URL).
 *   4. Zips the staged `upload/` directory into
 *      `release/mintcart-<version>.zip`.
 *
 * Version is read from `MintCart\Core\Version::VERSION`
 * (upload/system/library/mintcart/core/version.php) — the single source of
 * truth for the platform version, not re-typed here.
 *
 * Requires `git` and `zip` on PATH (both present in this repo's own CI
 * runner — see .github/workflows/ci.yml's ubuntu-latest jobs). Packages
 * the current `HEAD` commit, not the working tree — commit changes first.
 *
 * Usage: npm run release  (== node scripts/release.js)
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const RELEASE_DIR = path.join(ROOT, 'release');
const STAGING_DIR = path.join(RELEASE_DIR, 'staging');

function run(command, args, options = {}) {
    execFileSync(command, args, { stdio: 'inherit', cwd: ROOT, ...options });
}

function readVersion() {
    const versionFile = path.join(
        ROOT,
        'upload/system/library/mintcart/core/version.php'
    );
    const contents = fs.readFileSync(versionFile, 'utf8');
    const match = contents.match(/VERSION\s*=\s*'([^']+)'/);

    if (!match) {
        throw new Error(`Could not find VERSION constant in ${versionFile}`);
    }

    return match[1];
}

function main() {
    const version = readVersion();
    const zipName = `mintcart-${version}.zip`;
    const zipPath = path.join(RELEASE_DIR, zipName);

    console.log(`MintCart release packaging — version ${version}`);

    console.log('\n[1/4] Building frontend assets (npm run build)...');
    run('npm', ['run', 'build']);

    console.log('\n[2/4] Staging committed upload/ contents (git archive HEAD)...');
    fs.rmSync(RELEASE_DIR, { recursive: true, force: true });
    fs.mkdirSync(STAGING_DIR, { recursive: true });
    // `git archive` writes a tar stream to stdout; pipe it straight into
    // `tar -x` rather than writing an intermediate .tar file to disk.
    execFileSync(
        `git archive --format=tar HEAD upload | tar -x -C "${STAGING_DIR}"`,
        { shell: true, stdio: 'inherit', cwd: ROOT }
    );

    console.log('\n[3/4] Copying built assets into upload/assets/dist/{css,js}...');
    const distDir = path.join(ROOT, 'assets/dist');
    // Pages load assets/dist/{css,js}/*.min.* (catalog and admin
    // common/header.php) - the package must use the same path.
    const uploadAssetsDir = path.join(STAGING_DIR, 'upload/assets/dist');

    for (const kind of ['css', 'js']) {
        const source = path.join(distDir, kind);
        const destination = path.join(uploadAssetsDir, kind);

        if (!fs.existsSync(source)) {
            throw new Error(
                `${source} does not exist — did "npm run build" fail silently?`
            );
        }

        fs.mkdirSync(destination, { recursive: true });
        fs.cpSync(source, destination, { recursive: true });
    }

    console.log('\n[4/4] Zipping upload/ into ' + path.relative(ROOT, zipPath) + '...');
    run('zip', ['-r', '-q', zipPath, 'upload'], { cwd: STAGING_DIR });

    fs.rmSync(STAGING_DIR, { recursive: true, force: true });

    const { size } = fs.statSync(zipPath);
    console.log(
        `\nDone: ${path.relative(ROOT, zipPath)} (${(size / 1024 / 1024).toFixed(2)} MB)`
    );
}

main();
