<?php
/**
 * Standalone smoke test for MintCart\Compatibility\CompatibilityChecker.
 *
 * No PHPUnit/Composer in the repo yet, same as store-readiness-test.php.
 * CompatibilityChecker has no OpenCart dependency by design, so this runs
 * entirely against hand-built manifest arrays (the same shape
 * InstallManifestParser::parse() returns) rather than going through XML.
 *
 * Run: php tests/mintcart/compatibility/compatibility-checker-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/compatibility/compatibilitychecker.php';

use MintCart\Compatibility\CompatibilityChecker;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$checker = new CompatibilityChecker();

// 1. A completely clean, brand-new extension file: no controller/template
//    classification issue, no Bootstrap 3 markers, no conflicts — still
//    reports a full, well-shaped summary rather than an empty result.
$manifest1 = [
    'files' => [
        ['path' => 'catalog/controller/extension/module/foo.php', 'operation_count' => 1, 'content' => 'echo "hello";'],
    ],
];
$report1 = $checker->check($manifest1);
assertSame(1, $report1['summary']['file_count'], 'check: file_count for a single clean file', $failures);
assertSame(1, $report1['summary']['controller_count'], 'check: a /controller/ path is classified as a controller', $failures);
assertSame(0, $report1['summary']['template_count'], 'check: a .php controller is not a template', $failures);
assertSame(0, $report1['summary']['bootstrap3_count'], 'check: clean content -> no Bootstrap 3 assumptions flagged', $failures);
assertSame(0, $report1['summary']['conflict_count'], 'check: a brand-new module file is not a known MintCart-modified path', $failures);
assertSame(['catalog/controller/extension/module/foo.php'], $report1['controller_files'], 'check: controller_files lists the one controller', $failures);

// 2. A .twig template and a legacy .tpl template are both classified as
//    templates (is_template), and neither is a controller.
$manifest2 = [
    'files' => [
        ['path' => 'catalog/view/theme/default/template/product/product.tpl', 'operation_count' => 1, 'content' => ''],
        ['path' => 'catalog/view/theme/mint_default/template/mint/components/product-card.twig', 'operation_count' => 1, 'content' => ''],
    ],
];
$report2 = $checker->check($manifest2);
assertSame(2, $report2['summary']['template_count'], 'check: both .tpl and .twig files are classified as templates', $failures);
assertSame(0, $report2['summary']['controller_count'], 'check: template files are not controllers', $failures);
assertSame(2, $report2['summary']['theme_count'], 'check: files under view/theme/ are classified as theme files', $failures);

// 3. Content mentioning known Bootstrap 3 leaf classes is flagged, with
//    the specific markers that matched named in the result.
$manifest3 = [
    'files' => [
        [
            'path' => 'catalog/view/theme/default/template/common/footer.tpl',
            'operation_count' => 1,
            'content' => '<div class="panel panel-heading pull-right"></div>',
        ],
    ],
];
$report3 = $checker->check($manifest3);
assertSame(1, $report3['summary']['bootstrap3_count'], 'check: one file flagged for Bootstrap 3 markers', $failures);
assertSame('catalog/view/theme/default/template/common/footer.tpl', $report3['bootstrap3_assumptions'][0]['path'], 'check: flagged file path', $failures);
assertTrue(in_array('panel', $report3['bootstrap3_assumptions'][0]['markers'], true), 'check: "panel" marker detected', $failures);
assertTrue(in_array('panel-heading', $report3['bootstrap3_assumptions'][0]['markers'], true), 'check: "panel-heading" marker detected', $failures);
assertTrue(in_array('pull-right', $report3['bootstrap3_assumptions'][0]['markers'], true), 'check: "pull-right" marker detected', $failures);
assertTrue(!in_array('btn-default', $report3['bootstrap3_assumptions'][0]['markers'], true), 'check: markers absent from content are not reported', $failures);

// 4. A file path matching one of MintCart's own known upstream-diff files
//    is flagged as a potential conflict.
$manifest4 = [
    'files' => [
        ['path' => 'catalog/controller/product/category.php', 'operation_count' => 1, 'content' => ''],
        ['path' => 'catalog/controller/extension/module/foo.php', 'operation_count' => 1, 'content' => ''],
    ],
];
$report4 = $checker->check($manifest4);
assertSame(['catalog/controller/product/category.php'], $report4['potential_conflicts'], 'check: known MintCart-modified file flagged as a conflict', $failures);
assertSame(1, $report4['summary']['conflict_count'], 'check: conflict_count matches potential_conflicts', $failures);

// 5. Caller-supplied extra conflict paths are honored on top of the
//    built-in known-modified list.
$manifest5 = [
    'files' => [
        ['path' => 'catalog/controller/product/product.php', 'operation_count' => 1, 'content' => ''],
    ],
];
$report5 = $checker->check($manifest5, ['catalog/controller/product/product.php']);
assertSame(['catalog/controller/product/product.php'], $report5['potential_conflicts'], 'check: extra conflict path supplied by caller is honored', $failures);

// 6. Never blocks/throws — an empty manifest still returns a fully-shaped,
//    zeroed-out report rather than an error.
$report6 = $checker->check(['files' => []]);
assertSame(0, $report6['summary']['file_count'], 'check: empty manifest -> file_count 0', $failures);
assertSame([], $report6['files'], 'check: empty manifest -> empty files list', $failures);
assertSame([], $report6['potential_conflicts'], 'check: empty manifest -> no conflicts', $failures);
assertSame([], $report6['bootstrap3_assumptions'], 'check: empty manifest -> no Bootstrap 3 assumptions', $failures);

// 7. A file entry missing the optional 'content'/'operation_count' keys
//    (defensive against a hand-built or partial manifest) doesn't warn
//    or throw.
$manifest7 = ['files' => [['path' => 'catalog/controller/product/product.php']]];
$report7 = $checker->check($manifest7);
assertSame(1, $report7['summary']['file_count'], 'check: file entry missing content/operation_count is still counted', $failures);
assertSame([], $report7['bootstrap3_assumptions'], 'check: missing content is treated as empty, not scanned', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: CompatibilityChecker smoke test (7 scenarios)\n";
exit(0);
