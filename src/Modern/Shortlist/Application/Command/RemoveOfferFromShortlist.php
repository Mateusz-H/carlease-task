<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Application\Command;

final readonly class RemoveOfferFromShortlist
{
    public function __construct(
        public string $visitorSessionId,
        public string $offerId,
    ) {
    }
}
