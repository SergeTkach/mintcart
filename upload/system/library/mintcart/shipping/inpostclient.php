<?php
namespace MintCart\Shipping;

/**
 * InPost points API (ShipX "points"): the parcel lockers nearest to a postcode.
 * One class for every InPost country; only the base URL differs
 * (UK: https://api-uk-points.easypack24.net/v1).
 *
 * No OpenCart dependency: the HTTP call is an injected callable
 * `fn(string $url, array $headers): ?string`, so the parsing is tested without
 * the network.
 */
final class InpostClient
{
    public const MAX_DISTANCE = 10000;

    public const PER_PAGE = 25;

    private string $baseUrl;

    private string $token;

    /** @var callable */
    private $transport;

    public function __construct(string $baseUrl, string $token, callable $transport)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->transport = $transport;
    }

    /**
     * @return list<array{id: string, text: string, city: string, address: string}>
     *         `id` = the locker's name (e.g. "UKL123"), `address` = the street line
     */
    public function searchPoints(string $postcode): array
    {
        $postcode = strtoupper(trim(preg_replace('/\s+/', ' ', $postcode) ?? ''));

        if ($postcode === '' || strlen($postcode) > 12) {
            return [];
        }

        $url = $this->baseUrl . '/points?' . http_build_query([
            'relative_post_code' => $postcode,
            'max_distance'       => self::MAX_DISTANCE,
            'per_page'           => self::PER_PAGE,
            'type'               => 'parcel_locker',
            'status'             => 'Operating',
        ]);

        $headers = ['Accept: application/json'];

        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $raw = ($this->transport)($url, $headers);
        $response = $raw === null ? null : json_decode($raw, true);

        if (!is_array($response) || !is_array($response['items'] ?? null)) {
            return [];
        }

        $points = [];

        foreach ($response['items'] as $row) {
            $name = trim((string)($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $details = is_array($row['address_details'] ?? null) ? $row['address_details'] : [];
            $address = is_array($row['address'] ?? null) ? $row['address'] : [];

            $street = trim((string)($address['line1'] ?? trim(($details['street'] ?? '') . ' ' . ($details['building_number'] ?? ''))));
            $city = trim((string)($details['city'] ?? ''));
            $code = trim((string)($details['post_code'] ?? ''));
            $where = trim((string)($row['location_description'] ?? ''));

            $parts = array_filter([$street, $city, $code], static fn ($part) => $part !== '');

            $points[] = [
                'id'      => $name,
                'text'    => $name . ($parts ? ': ' . implode(', ', $parts) : '') . ($where !== '' ? ' (' . $where . ')' : ''),
                'city'    => $city,
                'address' => $street !== '' ? $street : $name,
            ];
        }

        return $points;
    }
}
