<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Domain;

final readonly class OfferAddedToShortlist
{
    public function __construct(
        public string $visitorSessionId,
        public string $offerId,
    ) {
    }
}
