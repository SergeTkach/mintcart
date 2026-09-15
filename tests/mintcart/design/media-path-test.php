<?php
/**
 * Standalone smoke test for MintCart\Design\MediaPath.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. MediaPath has no OpenCart dependency by design, so this runs
 * entirely against plain strings — no real filesystem access.
 *
 * Run: php tests/mintcart/design/media-path-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/mediapath.php';

use MintCart\Design\MediaPath;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. sanitizeFilterName() strips wildcard/path-separator characters and a
// trailing slash, matching common/filemanager.php's own filter_name rule.
assertSame('shirts', MediaPath::sanitizeFilterName('shirts'), 'sanitizeFilterName: plain term passes through', $failures);
assertSame('....etcpasswd', MediaPath::sanitizeFilterName('../../etc/passwd'), 'sanitizeFilterName: strips path separators (dots are left alone, matching filemanager.php — traversal safety comes from isWithinCatalog(), not this method)', $failures);
assertSame('shirts', MediaPath::sanitizeFilterName('shirts/'), 'sanitizeFilterName: trims a trailing slash', $failures);
assertSame('shirts', MediaPath::sanitizeFilterName('*shirts*'), 'sanitizeFilterName: strips glob wildcards', $failures);

// 2. isWithinCatalog(): the realpath'd target must start with the
// realpath'd catalog root, after normalizing to forward slashes.
assertSame(true, MediaPath::isWithinCatalog('/var/www/image/catalog/shirts', '/var/www/image/catalog'), 'isWithinCatalog: a subdirectory of the catalog root is within it', $failures);
assertSame(true, MediaPath::isWithinCatalog('/var/www/image/catalog', '/var/www/image/catalog'), 'isWithinCatalog: the catalog root itself is within it', $failures);
assertSame(false, MediaPath::isWithinCatalog('/var/www/image/other', '/var/www/image/catalog'), 'isWithinCatalog: a sibling directory is not within the catalog root', $failures);
assertSame(false, MediaPath::isWithinCatalog('/etc/passwd', '/var/www/image/catalog'), 'isWithinCatalog: a traversal-resolved path outside the root is rejected', $failures);
assertSame(true, MediaPath::isWithinCatalog('C:\\www\\image\\catalog\\shirts', 'C:\\www\\image\\catalog'), 'isWithinCatalog: backslash paths (Windows) are normalized before comparison', $failures);
assertSame(false, MediaPath::isWithinCatalog('/var/www/image/catalog-evil/x', '/var/www/image/catalog'), 'isWithinCatalog: a same-prefix sibling ("catalog-evil") is not a false positive', $failures);
assertSame(false, MediaPath::isWithinCatalog('/var/www/image/catalog', ''), 'isWithinCatalog: an empty catalog root never matches anything', $failures);

// 3. sanitizeName(): HTML-entity-decodes then takes only the basename —
// matches common/filemanager.php's own filename/folder sanitization.
assertSame('shirt.jpg', MediaPath::sanitizeName('shirt.jpg'), 'sanitizeName: plain filename passes through', $failures);
assertSame('shirt.jpg', MediaPath::sanitizeName('../../shirt.jpg'), 'sanitizeName: a path is reduced to its basename', $failures);
assertSame("shirt's.jpg", MediaPath::sanitizeName('shirt&#039;s.jpg'), 'sanitizeName: HTML entities are decoded first', $failures);

// 4. isValidFilenameLength(): 3-255 characters inclusive.
assertSame(false, MediaPath::isValidFilenameLength('ab'), 'isValidFilenameLength: 2 characters is too short', $failures);
assertSame(true, MediaPath::isValidFilenameLength('abc'), 'isValidFilenameLength: 3 characters is the minimum accepted', $failures);
assertSame(true, MediaPath::isValidFilenameLength(str_repeat('a', 255)), 'isValidFilenameLength: 255 characters is the maximum accepted', $failures);
assertSame(false, MediaPath::isValidFilenameLength(str_repeat('a', 256)), 'isValidFilenameLength: 256 characters is too long', $failures);

// 5. isValidFolderNameLength(): 3-128 characters inclusive.
assertSame(false, MediaPath::isValidFolderNameLength('ab'), 'isValidFolderNameLength: 2 characters is too short', $failures);
assertSame(true, MediaPath::isValidFolderNameLength(str_repeat('a', 128)), 'isValidFolderNameLength: 128 characters is the maximum accepted', $failures);
assertSame(false, MediaPath::isValidFolderNameLength(str_repeat('a', 129)), 'isValidFolderNameLength: 129 characters is too long', $failures);

// 6. hasAllowedExtension(): matches common/filemanager.php's own whitelist,
// case-insensitively, and rejects anything else (including no extension).
assertSame(true, MediaPath::hasAllowedExtension('shirt.jpg'), 'hasAllowedExtension: .jpg is allowed', $failures);
assertSame(true, MediaPath::hasAllowedExtension('shirt.WEBP'), 'hasAllowedExtension: extension check is case-insensitive', $failures);
assertSame(false, MediaPath::hasAllowedExtension('shirt.php'), 'hasAllowedExtension: .php is rejected', $failures);
assertSame(false, MediaPath::hasAllowedExtension('shirt.php.jpg.php'), 'hasAllowedExtension: only the final extension is checked, and it must itself be allowed', $failures);
assertSame(false, MediaPath::hasAllowedExtension('noextension'), 'hasAllowedExtension: a filename with no extension is rejected', $failures);

// 7. hasAllowedMimeType(): matches common/filemanager.php's own whitelist.
assertSame(true, MediaPath::hasAllowedMimeType('image/jpeg'), 'hasAllowedMimeType: image/jpeg is allowed', $failures);
assertSame(true, MediaPath::hasAllowedMimeType('image/webp'), 'hasAllowedMimeType: image/webp is allowed', $failures);
assertSame(false, MediaPath::hasAllowedMimeType('application/x-php'), 'hasAllowedMimeType: a non-image mime type is rejected', $failures);
assertSame(false, MediaPath::hasAllowedMimeType('image/svg+xml'), 'hasAllowedMimeType: SVG is deliberately not on the whitelist (matches filemanager.php)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: MediaPath smoke test (' . 7 . " scenarios)\n";
exit(0);
