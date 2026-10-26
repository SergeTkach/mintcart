<?php
namespace MintCart\Onboarding;

/**
 * The currencies the Setup Wizard's "Language & currency" step offers
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §2: pick a currency from a list, add it
 * with one click) - a bundled ISO 4217 subset (`system/mintcart/data/currencies.json`:
 * title, symbol, symbol side, decimals), so no network is needed to show it.
 *
 * Exchange rates are a separate, best-effort step: `rate()` converts a table
 * of rates against any common base into OpenCart's convention, "units of this
 * currency per 1 unit of the default currency". The tables come from the
 * European Central Bank's daily reference rates (~30 currencies) and, for
 * the rest (UAH, KZT, ...), from open.er-api.com (free, no key, ~160
 * currencies; rates by ExchangeRate-API). `fetchEcbRates()` and
 * `fetchOpenRates()` are the only parts that touch the network; each returns
 * null on any failure and the caller falls back to a rate of 1 that the
 * store owner corrects in Localisation -> Currencies.
 *
 * No OpenCart dependency, same shape as WizardProgress/StoreReadiness.
 */
final class CurrencyCatalog
{
    private const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    private const OPEN_RATES_URL = 'https://open.er-api.com/v6/latest/USD';

    /** @var array<string, array{title: string, symbol: string, side: string, decimals: int}> */
    private $currencies;

    /**
     * @param array<string, array{title: string, symbol: string, side: string, decimals: int}> $currencies
     */
    public function __construct(array $currencies)
    {
        $this->currencies = $currencies;
    }

    public static function fromFile(string $file): self
    {
        $data = json_decode((string)@file_get_contents($file), true);

        return new self(is_array($data) ? $data : []);
    }

    /**
     * Catalog entries the store does not have yet, as code => "Title (CODE)".
     *
     * @param string[] $installedCodes
     * @return array<string, string>
     */
    public function available(array $installedCodes): array
    {
        $installed = array_map('strtoupper', $installedCodes);
        $list = [];

        foreach ($this->currencies as $code => $currency) {
            if (!in_array($code, $installed, true)) {
                $list[$code] = $currency['title'] . ' (' . $code . ')';
            }
        }

        ksort($list);

        return $list;
    }

    /**
     * The values OpenCart's currency table needs, or null for an unknown code.
     *
     * @return array{title: string, code: string, symbol_left: string, symbol_right: string, decimal_place: int}|null
     */
    public function find(string $code): ?array
    {
        $code = strtoupper(trim($code));

        if (!isset($this->currencies[$code])) {
            return null;
        }

        $currency = $this->currencies[$code];

        return [
            'title'         => $currency['title'],
            'code'          => $code,
            'symbol_left'   => $currency['side'] === 'l' ? $currency['symbol'] : '',
            'symbol_right'  => $currency['side'] === 'r' ? $currency['symbol'] : '',
            'decimal_place' => (int)$currency['decimals'],
        ];
    }

    /**
     * @param array<string, float> $ecbRates currency code => units per 1 unit of a common base (the base itself is 1)
     */
    public static function rate(array $ecbRates, string $code, string $baseCode): ?float
    {
        $code = strtoupper($code);
        $baseCode = strtoupper($baseCode);

        if (empty($ecbRates[$code]) || empty($ecbRates[$baseCode])) {
            return null;
        }

        return $ecbRates[$code] / $ecbRates[$baseCode];
    }

    /**
     * @return array<string, float>|null
     */
    public static function parseEcbXml(string $xml): ?array
    {
        if (!preg_match_all('/currency=[\'"]([A-Z]{3})[\'"]\s+rate=[\'"]([0-9.]+)[\'"]/', $xml, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $rates = ['EUR' => 1.0];

        foreach ($matches as $match) {
            $rates[$match[1]] = (float)$match[2];
        }

        return $rates;
    }

    /**
     * @return array<string, float>|null null when the ECB cannot be reached
     */
    public static function fetchEcbRates(): ?array
    {
        $xml = self::download(self::ECB_URL);

        return $xml !== null ? self::parseEcbXml($xml) : null;
    }

    /**
     * @return array<string, float>|null null when the service cannot be reached
     */
    public static function fetchOpenRates(): ?array
    {
        $json = self::download(self::OPEN_RATES_URL);
        $data = $json !== null ? json_decode($json, true) : null;

        if (!is_array($data) || ($data['result'] ?? '') !== 'success' || !is_array($data['rates'] ?? null)) {
            return null;
        }

        return array_map('floatval', $data['rates']);
    }

    private static function download(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 6,
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return is_string($body) && $status === 200 ? $body : null;
    }
}
