<?php

/**
 * Regression test for a real MintCart bug (not a historical OpenCart one,
 * so it doesn't go through `docs/fixes/registry.md`'s historical-fix
 * process): `ControllerMintcartWizard::toggleDelivery()`,
 * `ControllerMintcartBusinessDelivery::toggle()` and
 * `ControllerMintcartBusinessPayments::toggle()` all built their AJAX
 * toggle route with a dot separating the controller path from the method
 * name (e.g. `mintcart/business/delivery.toggle`), on the mistaken
 * assumption that OpenCart 3 admin routing accepts dot notation the way
 * OpenCart 4 does.
 *
 * It does not. `ControllerStartupRouter::index()`
 * (`upload/admin/controller/startup/router.php`) sanitizes the incoming
 * `route` query parameter with
 * `preg_replace('/[^a-zA-Z0-9_\/]/', '', $route)` *before* handing it to
 * `Action` (`upload/system/engine/action.php`) - the dot is stripped
 * outright, silently merging the controller path and method name into one
 * unresolvable segment (`mintcart/business/delivery.toggle` becomes
 * `mintcart/business/deliverytoggle`). `Action`'s own constructor then
 * walks back through `/`-separated segments looking for a real
 * `controller/*.php` file and never finds one, so the call resolves to a
 * dead route (`Action::execute()`'s "Error: Could not call ..." branch) -
 * these three POST toggle actions never actually ran. Verified by
 * simulating the exact router + Action logic against the real strings
 * this test scans for, before the fix (`docs/IMPLEMENTATION_STATUS.md`'s
 * Phase 14/13 trailer notes have the full writeup).
 *
 * The fix is mechanical (dot -> slash, matching every other admin route in
 * this codebase - `grep` confirms zero other `url->link()` call anywhere
 * under `upload/` used dot notation before this fix). This test is a
 * permanent regression guard, not a one-off: it scans every admin
 * controller for the same mistake, not just the three files that had it,
 * so a future PR reintroducing this pattern (a real risk - PR #142's
 * open `togglePayment()`, not yet merged as of this fix, copies
 * `toggleDelivery()`'s shape verbatim and needs the same slash fix when
 * it lands) fails CI instead of shipping a dead route.
 *
 * Run: php tests/mintcart/admin/route-format-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

$root = __DIR__ . '/../../../upload/admin/controller';

if (!is_dir($root)) {
    fwrite(STDERR, "FAIL: {$root} not found\n");
    exit(1);
}

$failures = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $contents = file_get_contents($file->getPathname());

    // Matches the first argument of every url->link('...') call and flags
    // one containing a dot between two route-path characters - the exact
    // shape ControllerStartupRouter::index()'s sanitizer breaks. A dot
    // elsewhere (e.g. inside a second argument, a URL query string, or a
    // version number) is not this bug and is not matched, since the
    // pattern only looks inside url->link('...')'s first, single-quoted
    // argument.
    if (preg_match_all("/url->link\('([a-zA-Z0-9_\/]+\.[a-zA-Z][a-zA-Z0-9_\/]*)'/", $contents, $matches)) {
        foreach ($matches[1] as $route) {
            $failures[] = $file->getPathname() . ": url->link('{$route}', ...) uses dot notation, "
                . "which OpenCart 3's admin router strips before resolving the controller/method "
                . "(use a slash instead, e.g. '" . str_replace('.', '/', $route) . "')";
        }
    }
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " admin route(s) use broken dot notation:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: no admin controller uses OpenCart-3-incompatible dot-notation routes\n";
exit(0);
