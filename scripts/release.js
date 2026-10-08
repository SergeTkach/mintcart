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

const IS_WINDOWS = process.platform === 'win32';

function run(command, args, options = {}) {
    // npm is npm.cmd on Windows, which execFileSync can only start via a shell.
    execFileSync(command, args, { stdio: 'inherit', cwd: ROOT, shell: IS_WINDOWS && command === 'npm', ...options });
}

function commandExists(command) {
    try {
        execFileSync(IS_WINDOWS ? 'where' : 'which', [command], { stdio: 'ignore' });
        return true;
    } catch {
        return false;
    }
}

// `zip` when available; otherwise Windows' own bsdtar (System32\tar.exe,
// Windows 10+), which writes zip archives with -a.
function zipDirectory(zipPath, directory, cwd) {
    if (commandExists('zip')) {
        run('zip', ['-r', '-q', zipPath, directory], { cwd });
        return;
    }

    if (IS_WINDOWS) {
        const bsdtar = path.join(process.env.SystemRoot || 'C:\\Windows', 'System32', 'tar.exe');

        run(bsdtar, ['-a', '-c', '-f', zipPath, directory], { cwd });
        return;
    }

    throw new Error('No "zip" command found - install zip and run again.');
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
    // Write the archive to a file and extract it without a shell pipe: on
    // Windows a shell resolves `tar` to Git's GNU tar, which mangles
    // C:\ paths; System32's bsdtar handles them.
    const tarPath = path.join(RELEASE_DIR, 'upload.tar');
    const tarCommand = IS_WINDOWS
        ? path.join(process.env.SystemRoot || 'C:\\Windows', 'System32', 'tar.exe')
        : 'tar';

    run('git', ['archive', '--format=tar', '-o', tarPath, 'HEAD', 'upload']);
    run(tarCommand, ['-x', '-f', tarPath, '-C', STAGING_DIR]);
    fs.rmSync(tarPath);

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
        // Only the minified files pages load; assets/dist may also hold
        // dev builds and source maps from `npm run dev`.
        for (const file of fs.readdirSync(source)) {
            if (/\.min\.(css|js)$/.test(file)) {
                fs.copyFileSync(path.join(source, file), path.join(destination, file));
            }
        }
    }

    console.log('\n[4/4] Zipping upload/ into ' + path.relative(ROOT, zipPath) + '...');
    zipDirectory(zipPath, 'upload', STAGING_DIR);

    fs.rmSync(STAGING_DIR, { recursive: true, force: true });

    const { size } = fs.statSync(zipPath);
    console.log(
        `\nDone: ${path.relative(ROOT, zipPath)} (${(size / 1024 / 1024).toFixed(2)} MB)`
    );
}

main();
