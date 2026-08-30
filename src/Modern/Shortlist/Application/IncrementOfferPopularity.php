<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Application;

use App\Modern\Shortlist\Application\Port\OfferPopularityCounterInterface;
use App\Modern\Shortlist\Domain\OfferAddedToShortlist;
use Ecotone\Messaging\Attribute\Asynchronous;
use Ecotone\Modelling\Attribute\EventHandler;

/**
 * The popularity port is not idempotent, so redelivery matters here: the Dbal
 * module's message-id deduplication (on by default) drops a redelivered copy
 * before this handler runs. See DECISIONS.md for the residual window.
 */
final readonly class IncrementOfferPopularity
{
    public function __construct(private OfferPopularityCounterInterface $popularityCounter)
    {
    }

    #[Asynchronous('async')]
    #[EventHandler(endpointId: 'shortlist.increment_offer_popularity')]
    public function whenOfferAdded(OfferAddedToShortlist $event): void
    {
        $this->popularityCounter->increment($event->offerId);
    }
}
