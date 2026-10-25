<?php
namespace MintCart\Shipping;

/**
 * Nova Poshta JSON API, the two calls the checkout needs: city search
 * (`Address.searchSettlements`) and the pickup points of a city
 * (`AddressGeneral.getWarehouses`). Live calls, no local copy of the city or
 * branch tables to sync; callers cache the results.
 *
 * No OpenCart dependency: the HTTP call is an injected callable
 * `fn(string $url, string $json): ?string`, so the parsing is tested without
 * the network.
 */
final class NovaPoshtaClient
{
    /** TypeOfWarehouse of a parcel locker (postomat); every other type is a branch. */
    public const POSTOMAT_TYPE = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';

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
     * @return list<array{id: string, name: string, text: string}> id = the
     *         city ref the pickup points are asked for; text includes the region
     */
    public function searchCities(string $term, int $limit = 20): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $data = $this->call('Address', 'searchSettlements', ['CityName' => $term, 'Limit' => (string)$limit]);
        $cities = [];

        foreach ($data[0]['Addresses'] ?? [] as $row) {
            if (empty($row['DeliveryCity'])) {
                continue;
            }

            $cities[] = [
                'id'   => (string)$row['DeliveryCity'],
                'name' => (string)($row['MainDescription'] ?? ''),
                'text' => (string)($row['Present'] ?? $row['MainDescription'] ?? ''),
            ];
        }

        return $cities;
    }

    /**
     * @return list<array{id: string, text: string, postomat: bool}>
     */
    public function getPoints(string $cityRef): array
    {
        $cityRef = trim($cityRef);

        if ($cityRef === '') {
            return [];
        }

        $points = [];

        foreach ($this->call('AddressGeneral', 'getWarehouses', ['CityRef' => $cityRef, 'Limit' => '1000']) as $row) {
            if (empty($row['Ref']) || empty($row['Description'])) {
                continue;
            }

            $points[] = [
                'id'       => (string)$row['Ref'],
                'text'     => (string)$row['Description'],
                'postomat' => ($row['TypeOfWarehouse'] ?? '') === self::POSTOMAT_TYPE,
            ];
        }

        return $points;
    }

    /**
     * @return array the response's `data`, [] on any failure (no key, network
     *         error, `success: false`) so the checkout degrades to an empty list
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
            'methodProperties' => $properties,
        ]);

        $raw = ($this->transport)($this->url, (string)$body);
        $response = $raw === null ? null : json_decode($raw, true);

        if (!is_array($response) || empty($response['success']) || !is_array($response['data'] ?? null)) {
            return [];
        }

        return $response['data'];
    }
}
