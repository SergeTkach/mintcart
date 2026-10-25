<?php
/**
 * Standalone smoke test for MintCart\Commerce\PhoneMask.
 *
 * Run: php tests/mintcart/commerce/phone-mask-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/phonemask.php';

use MintCart\Commerce\PhoneMask;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$defaults = json_decode((string)file_get_contents(__DIR__ . '/../../../upload/system/mintcart/data/phone-masks.json'), true);

$check(PhoneMask::resolve('', 'ua', $defaults) === '+380 (##) ###-##-##', 'country default, case-insensitive');
$check(PhoneMask::resolve(' +49 #### ####### ', 'UA', $defaults) === '+49 #### #######', 'custom mask wins and is trimmed');
$check(PhoneMask::resolve('', 'XX', $defaults) === '', 'unknown country has no mask');
$check(PhoneMask::isValid('+380 (##) ###-##-##'), 'valid mask');
$check(!PhoneMask::isValid('abc ###'), 'letters rejected');
$check(!PhoneMask::isValid('+380'), 'mask without # rejected');
foreach ($defaults as $iso => $mask) {
    $check(PhoneMask::isValid($mask), "bundled mask $iso is valid");
}

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
