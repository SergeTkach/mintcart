<?php
namespace MintCart\Shipping;

/**
 * Nova Poshta JSON API, the three calls that refill the local city and branch
 * directory: regions (`Address.getAreas`), cities (`Address.getCities`) and
 * branches/lockers (`AddressGeneral.getWarehouses`), the last two page by page.
 * The checkout itself reads the local copy (migration
 * 202611081000_create_novaposhta_directory.php), never the API.
 *
 * No OpenCart dependency: the HTTP call is an injected callable
 * `fn(string $url, string $json): ?string`, so the parsing is tested without
 * the network.
 */
final class NovaPoshtaClient
{
    /** TypeOfWarehouse of a parcel locker (postomat); every other type is a branch. */
    public const POSTOMAT_TYPE = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';

    public const PAGE_SIZE = 500;

    private string $apiKey;

    /** @var callable */
    private $transport;

    private string $url;

    public function __construct(string $apiKey, callable $transport, string $url = 'https://api.novaposhta.ua/v2.0/json/')
    {
        $this->apiKey = $apiKey;
        $this->transport = $transport;
        $this->url = $url;
    }

    /**
     * @return array<string, array{name: string, name_ru: string}> by region ref
     */
    public function getAreas(): array
    {
        $areas = [];

        foreach ($this->call('Address', 'getAreas', []) as $row) {
            if (!empty($row['Ref'])) {
                $areas[(string)$row['Ref']] = [
                    'name'    => (string)($row['Description'] ?? ''),
                    'name_ru' => (string)($row['DescriptionRu'] ?? ''),
                ];
            }
        }

        return $areas;
    }

    /**
     * @return array{rows: list<array{city_ref: string, name: string, name_ru: string, area_ref: string}>, raw: int}
     *         `raw` = rows the API returned (a page shorter than PAGE_SIZE is the last)
     */
    public function getCitiesPage(int $page): array
    {
        $data = $this->call('Address', 'getCities', ['Page' => (string)$page, 'Limit' => (string)self::PAGE_SIZE]);
        $rows = [];

        foreach ($data as $row) {
            if (empty($row['Ref']) || empty($row['Description'])) {
                continue;
            }

            $rows[] = [
                'city_ref' => (string)$row['Ref'],
                'name'     => (string)$row['Description'],
                'name_ru'  => (string)($row['DescriptionRu'] ?? ''),
                'area_ref' => (string)($row['Area'] ?? ''),
            ];
        }

        return ['rows' => $rows, 'raw' => count($data)];
    }

    /**
     * @return array{rows: list<array{point_ref: string, city_ref: string, number: int, postomat: bool, description: string, description_ru: string}>, raw: int}
     */
    public function getWarehousesPage(int $page): array
    {
        $data = $this->call('AddressGeneral', 'getWarehouses', ['Page' => (string)$page, 'Limit' => (string)self::PAGE_SIZE, 'Language' => 'UA']);
        $rows = [];

        foreach ($data as $row) {
            if (empty($row['Ref']) || empty($row['CityRef']) || empty($row['Description'])) {
                continue;
            }

            $rows[] = [
                'point_ref'      => (string)$row['Ref'],
                'city_ref'       => (string)$row['CityRef'],
                'number'         => (int)($row['Number'] ?? 0),
                'postomat'       => ($row['TypeOfWarehouse'] ?? '') === self::POSTOMAT_TYPE,
                'description'    => (string)$row['Description'],
                'description_ru' => (string)($row['DescriptionRu'] ?? ''),
            ];
        }

        return ['rows' => $rows, 'raw' => count($data)];
    }

    /**
     * @return array the response's `data`, [] on any failure (no key, network
     *         error, `success: false`)
     */
    private function call(string $model, string $method, array $properties): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $body = json_encode([
            'apiKey'           => $this->apiKey,
            'modelName'        => $model,
            'calledMethod'     => $method,
            'methodProperties' => (object)$properties,
        ]);

        $raw = ($this->transport)($this->url, (string)$body);
        $response = $raw === null ? null : json_decode($raw, true);

        if (!is_array($response) || empty($response['success']) || !is_array($response['data'] ?? null)) {
            return [];
        }

        return $response['data'];
    }
}
