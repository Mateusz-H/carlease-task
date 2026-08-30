<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Application\Port;

/**
 * Write port over the popularity counter.
 *
 * In production this is a remote call. It is NOT idempotent: calling increment()
 * twice for the same offer increments twice. Keep that in mind for the asynchronous
 * event handler.
 *
 * DO NOT CHANGE THIS INTERFACE. Implement against it.
 */
interface OfferPopularityCounterInterface
{
    public function increment(string $offerId): void;
}
