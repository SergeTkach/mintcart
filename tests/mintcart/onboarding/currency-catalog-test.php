<?php
/**
 * Standalone smoke test for MintCart\Onboarding\CurrencyCatalog (no network:
 * only the bundled catalog and the rate arithmetic are exercised).
 *
 * Run: php tests/mintcart/onboarding/currency-catalog-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/onboarding/currencycatalog.php';

use MintCart\Onboarding\CurrencyCatalog;

$failures = [];

function check(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

$catalog = CurrencyCatalog::fromFile(__DIR__ . '/../../../upload/system/mintcart/data/currencies.json');

check(isset($catalog->available([])['UAH']), 'UAH is in the bundled catalog', $failures);
check(!isset($catalog->available(['usd'])['USD']), 'installed codes are hidden, case-insensitively', $failures);

$uah = $catalog->find('uah');
check($uah !== null && $uah['code'] === 'UAH' && $uah['symbol_right'] === '₴' && $uah['symbol_left'] === '', 'UAH symbol goes on the right', $failures);

$usd = $catalog->find('USD');
check($usd !== null && $usd['symbol_left'] === '$' && $usd['symbol_right'] === '', 'USD symbol goes on the left', $failures);
check($catalog->find('XXX') === null, 'unknown code returns null', $failures);

$ecb = CurrencyCatalog::parseEcbXml("<Cube><Cube currency='USD' rate='1.25'/><Cube currency=\"PLN\" rate=\"5\"/></Cube>");
check($ecb !== null && $ecb['EUR'] === 1.0 && $ecb['USD'] === 1.25 && $ecb['PLN'] === 5.0, 'ECB XML is parsed and EUR is 1', $failures);
check(CurrencyCatalog::parseEcbXml('<html/>') === null, 'garbage is not parsed', $failures);

check(abs(CurrencyCatalog::rate($ecb, 'PLN', 'USD') - 4.0) < 1e-9, 'PLN per 1 USD = 5 / 1.25', $failures);
check(abs(CurrencyCatalog::rate($ecb, 'USD', 'USD') - 1.0) < 1e-9, 'default currency rate is 1', $failures);
check(CurrencyCatalog::rate($ecb, 'UAH', 'USD') === null, 'a currency the source lacks gives null', $failures);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "OK\n";
