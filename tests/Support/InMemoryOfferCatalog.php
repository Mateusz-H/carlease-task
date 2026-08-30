<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modern\Shortlist\Application\Port\OfferCatalogInterface;

/**
 * Test double standing in for the search index. Feel free to extend it, but keep the
 * contract of OfferCatalogInterface intact.
 */
final class InMemoryOfferCatalog implements OfferCatalogInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $documents = [];

    public static function withSampleOffers(): self
    {
        $catalog = new self();

        $catalog->add('off-1001', 'Toyota', 'Corolla', 1299.00);
        $catalog->add('off-1002', 'Skoda', 'Octavia', 1449.50);
        $catalog->add('off-1003', 'Volkswagen', 'Golf', 1599.00);

        return $catalog;
    }

    public function add(string $offerId, string $brand, string $model, float $monthlyInstalment): void
    {
        $this->documents[$offerId] = [
            'offerId' => $offerId,
            'brand' => $brand,
            'model' => $model,
            'monthlyInstalment' => $monthlyInstalment,
            'thumbnailUrl' => '/images/offer-placeholder.svg',
        ];
    }

    public function remove(string $offerId): void
    {
        unset($this->documents[$offerId]);
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
}
