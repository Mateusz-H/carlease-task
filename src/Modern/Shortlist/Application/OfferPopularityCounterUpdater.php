<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Application;

use App\Modern\Shortlist\Application\Port\OfferPopularityCounterInterface;
use App\Modern\Shortlist\Domain\OfferAddedToShortlist;
use Ecotone\Messaging\Attribute\Asynchronous;
use Ecotone\Modelling\Attribute\EventHandler;

final readonly class OfferPopularityCounterUpdater
{
    public function __construct(private OfferPopularityCounterInterface $popularityCounter)
    {
    }

    /**
     * Redelivery stance: the counter is NOT idempotent (see the port), so a redelivered
     * message must not reach it twice. We rely on Ecotone Dbal deduplication, enabled by
     * default in DbalConfiguration::createWithDefaults() (key = message id, 7-day
     * retention): a redelivered copy is dropped before this handler runs. A narrow window
     * remains — the remote increment succeeds but the dedup record/ack does not — and the
     * offer counts twice. Accepted: popularity is an approximate metric, and closing the
     * window would require changing the port, which is a fixed contract. Deliberately no
     * custom dedup table and no disabling of the built-in one.
     */
    #[Asynchronous('async')]
    #[EventHandler(endpointId: 'shortlist.increment_offer_popularity')]
    public function whenOfferAdded(OfferAddedToShortlist $event): void
    {
        $this->popularityCounter->increment($event->offerId);
    }
}
