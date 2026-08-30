<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Infrastructure;

use App\Modern\Shortlist\Application\Port\OfferCatalogInterface;

/**
 * Stand-in for the search index, so the web layer has something to display.
 * Production queries the real index; this returns a fixed set of offers.
 *
 * There are deliberately more offers here than a shortlist can hold, so the limit is
 * reachable by clicking rather than only in a test.
 */
final class FixtureOfferCatalog implements OfferCatalogInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $documents;

    public function __construct()
    {
        $this->documents = [
            'off-1001' => self::document('off-1001', 'Toyota', 'Corolla', 1299.00),
            'off-1002' => self::document('off-1002', 'Skoda', 'Octavia', 1449.50),
            'off-1003' => self::document('off-1003', 'Volkswagen', 'Golf', 1599.00),
            'off-1004' => self::document('off-1004', 'Kia', 'Ceed', 1189.00),
            'off-1005' => self::document('off-1005', 'Mazda', 'CX-30', 1749.00),
            'off-1006' => self::document('off-1006', 'Hyundai', 'i30', 1229.00),
            'off-1007' => self::document('off-1007', 'Renault', 'Megane', 1319.00),
            'off-1008' => self::document('off-1008', 'Ford', 'Focus', 1379.00),
            'off-1009' => self::document('off-1009', 'Peugeot', '308', 1409.00),
            'off-1010' => self::document('off-1010', 'Opel', 'Astra', 1269.00),
            'off-1011' => self::document('off-1011', 'Seat', 'Leon', 1349.00),
            'off-1012' => self::document('off-1012', 'Dacia', 'Duster', 1099.00),
        ];
    }

    public function findByOfferId(string $offerId): ?array
    {
        return $this->documents[$offerId] ?? null;
    }

    public function findManyByOfferIds(array $offerIds): array
    {
        $found = [];

        foreach ($offerIds as $offerId) {
            if (isset($this->documents[$offerId])) {
                $found[$offerId] = $this->documents[$offerId];
            }
        }

        return $found;
    }

    public function findAvailableOffers(): array
    {
        return $this->documents;
    }

    /** @return array<string, mixed> */
    private static function document(string $offerId, string $brand, string $model, float $instalment): array
    {
        return [
            'offerId' => $offerId,
            'brand' => $brand,
            'model' => $model,
            'monthlyInstalment' => $instalment,
            'thumbnailUrl' => '/images/offer-placeholder.svg',
        ];
    }
}
