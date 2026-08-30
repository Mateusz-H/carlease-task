<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Application\Port;

/**
 * Read port over the search index.
 *
 * This is the ONLY source of car data. There is no Doctrine behind it and there never
 * will be: the database stores the shortlist, the index stores what a car IS.
 *
 * DO NOT CHANGE THIS INTERFACE. Implement against it.
 */
interface OfferCatalogInterface
{
    /**
     * Returns the indexed document for the given offer, or null when the offer is no
     * longer in the index (withdrawn, sold, reindexed away).
     *
     * The returned shape is the raw index document:
     *
     *   [
     *     'offerId'          => 'off-1001',
     *     'brand'            => 'Toyota',
     *     'model'            => 'Corolla',
     *     'monthlyInstalment'=> 1299.00,
     *     'thumbnailUrl'     => '/images/offer-placeholder.svg',
     *   ]
     *
     * @return array<string, mixed>|null
     */
    public function findByOfferId(string $offerId): ?array;

    /**
     * Batch variant. Offers missing from the index are simply absent from the result.
     *
     * @param list<string>              $offerIds
     * @return array<string, array<string, mixed>> keyed by offerId
     */
    public function findManyByOfferIds(array $offerIds): array;

    /**
     * Everything the index currently holds — this is what the browse page lists.
     * Production paginates and filters here; the fixture returns its whole set.
     *
     * @return array<string, array<string, mixed>> keyed by offerId
     */
    public function findAvailableOffers(): array;
}
