<?php
/**
 * Standalone smoke test for MC-0014 (docs/fixes/registry.md).
 *
 * klarna_account.php/klarna_invoice.php used utf8_encode()/utf8_decode() to
 * convert between the Klarna XML-RPC API's ISO-8859-1 wire format and PHP's
 * internal UTF-8 strings. Both functions are deprecated as of PHP 8.2 (still
 * present, not removed, so this was a silent-log-spam issue rather than a
 * fatal one) and were replaced with the exact behavioral equivalent,
 * mb_convert_encoding(), per the fix.
 *
 * This test proves the replacement is byte-for-byte equivalent to the
 * function it replaces, using PHP's own (deprecated but still callable)
 * utf8_encode()/utf8_decode() as the oracle — it does not require an
 * OpenCart bootstrap, since the bug and its fix are pure string
 * transformations, not controller behavior.
 *
 * Run: php tests/mintcart/fixes/mc-0014-klarna-charset-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// Representative strings: plain ASCII, and Latin-1-range accented
// characters/symbols that actually appear in Klarna order data (customer
// names, currency symbols, German/Nordic product titles).
$latin1Samples = [
    '',
    'Standard shipping',
    "Order #12345 failed",
    "Caf\xe9 Racer",        // "Café Racer" in ISO-8859-1
    "Stra\xdfe",            // "Straße" in ISO-8859-1
    "Pr\xe9nom",            // "Prénom" in ISO-8859-1
    "\xa3" . '9.99',        // pound sign in ISO-8859-1
];

foreach ($latin1Samples as $i => $latin1) {
    $expected = @utf8_encode($latin1);
    $actual = mb_convert_encoding($latin1, 'UTF-8', 'ISO-8859-1');
    assertSame($expected, $actual, "utf8_encode replacement matches for sample #{$i}", $failures);
}

$utf8Samples = [
    '',
    'Standard shipping',
    'Café Racer',
    'Straße',
    'Prénom',
    "\xc2\xa3" . '9.99', // pound sign in UTF-8
];

foreach ($utf8Samples as $i => $utf8) {
    $expected = @utf8_decode($utf8);
    $actual = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
    assertSame($expected, $actual, "utf8_decode replacement matches for sample #{$i}", $failures);
}

// The two touched files no longer call the deprecated functions at all.
$touchedFiles = [
    __DIR__ . '/../../../upload/catalog/controller/extension/payment/klarna_account.php',
    __DIR__ . '/../../../upload/catalog/controller/extension/payment/klarna_invoice.php',
];

foreach ($touchedFiles as $file) {
    $source = file_get_contents($file);
    $hasDeprecatedCall = (bool) preg_match('/\butf8_(en|de)code\s*\(/', $source);
    assertSame(false, $hasDeprecatedCall, basename($file) . ' no longer calls utf8_encode()/utf8_decode()', $failures);

    $hasReplacementCall = (bool) preg_match('/\bmb_convert_encoding\s*\(/', $source);
    assertSame(true, $hasReplacementCall, basename($file) . ' calls mb_convert_encoding()', $failures);
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: MC-0014 Klarna charset fix smoke test (' . (count($latin1Samples) + count($utf8Samples) + count($touchedFiles) * 2) . " assertions)\n";
exit(0);
