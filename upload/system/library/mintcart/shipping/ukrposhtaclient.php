<?php
namespace MintCart\Shipping;

/**
 * Ukrposhta address classifier (`address-classifier-ws`): the regions and the
 * post offices of a region. The service has no city search, so the extension
 * keeps its own copy of cities and branches (filled region by region from the
 * admin screen) and searches that.
 *
 * No OpenCart dependency: the HTTP call is an injected callable
 * `fn(string $url, array $headers): ?string`.
 */
final class UkrposhtaClient
{
    private string $apiKey;

    /** @var callable */
    private $transport;

    private string $url;

    public function __construct(string $apiKey, callable $transport, string $url = 'https://www.ukrposhta.ua/address-classifier-ws/')
    {
        $this->apiKey = $apiKey;
        $this->transport = $transport;
        $this->url = rtrim($url, '/') . '/';
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function getRegions(): array
    {
        $regions = [];

        foreach ($this->entries('get_regions_by_region_ua', []) as $entry) {
            if (!empty($entry['REGION_ID'])) {
                $regions[] = ['id' => (int)$entry['REGION_ID'], 'name' => (string)($entry['REGION_UA'] ?? '')];
            }
        }

        return $regions;
    }

    /**
     * The cities and branches of one region, ready to store.
     *
     * @return array{
     *     cities: array<string, array{name: string, district: string}>,
     *     branches: list<array{post_code: string, city_ref: string, description: string, phone: string}>
     * } cities keyed by city ref
     */
    public function getRegionDirectory(int $regionId): array
    {
        $directory = ['cities' => [], 'branches' => []];

        foreach ($this->entries('get_postoffices_by_postindex', ['poRegionId' => (string)$regionId]) as $entry) {
            $district = (string)($entry['DISTRICT_UA'] ?? '');
            $city = (string)(($entry['PDCITY_UA'] ?? '') !== '' ? $entry['PDCITY_UA'] : ($entry['CITY_UA'] ?? ''));
            $postCode = (string)($entry['POSTCODE'] ?? $entry['POSTINDEX'] ?? $entry['PO_CODE'] ?? '');

            if ($district === '' || $city === '' || $postCode === '') {
                continue;
            }

            $cityRef = self::cityRef($regionId, $district, $city);
            $address = trim((string)($entry['ADDRESS'] ?? ''));

            $directory['cities'][$cityRef] = ['name' => $city, 'district' => $district];
            $directory['branches'][] = [
                'post_code'   => $postCode,
                'city_ref'    => $cityRef,
                'description' => $postCode . ' - ' . $city . ($address !== '' ? ', ' . $address : ''),
                'phone'       => (string)($entry['PHONE'] ?? ''),
            ];
        }

        return $directory;
    }

    public static function cityRef(int $regionId, string $district, string $city): string
    {
        return md5($regionId . '_' . $district . '_' . $city);
    }

    /**
     * @return list<array> the response's `Entries.Entry` list ([] on any failure;
     *         the API returns a bare object when there is exactly one entry)
     */
    private function entries(string $endpoint, array $query): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $query['apiKey'] = $this->apiKey;

        $raw = ($this->transport)($this->url . $endpoint . '?' . http_build_query($query), [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ]);

        $response = $raw === null ? null : json_decode($raw, true);
        $entries = is_array($response) ? ($response['Entries']['Entry'] ?? null) : null;

        if (!is_array($entries)) {
            return [];
        }

        return isset($entries[0]) ? $entries : [$entries];
    }
}
