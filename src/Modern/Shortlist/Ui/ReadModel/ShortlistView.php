<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Ui\ReadModel;

/**
 * The full picture of a visitor's shortlist: offers still present in the index, plus the
 * ids of saved offers the index no longer knows. The latter still occupy capacity slots,
 * so the view must show them (as "unavailable") and let the visitor remove them —
 * hiding them would make the counter lie and the limit unexplainable.
 */
final readonly class ShortlistView
{
    /** @param list<string> $unavailableOfferIds */
    public function __construct(
        public OfferViews $offers = new OfferViews(),
        public array $unavailableOfferIds = [],
    ) {
    }

    public function savedCount(): int
    {
        return count($this->offers) + count($this->unavailableOfferIds);
    }

    public function contains(string $offerId): bool
    {
        return $this->offers->contains($offerId) || in_array($offerId, $this->unavailableOfferIds, true);
    }
}
