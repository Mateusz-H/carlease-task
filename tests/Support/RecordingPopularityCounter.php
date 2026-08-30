<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modern\Shortlist\Application\Port\OfferPopularityCounterInterface;

/**
 * Test double for the popularity port. Records each increment() call.
 */
final class RecordingPopularityCounter implements OfferPopularityCounterInterface
{
    /** @var list<string> */
    private array $increments = [];

    public function increment(string $offerId): void
    {
        $this->increments[] = $offerId;
    }

    public function countFor(string $offerId): int
    {
        return count(array_filter($this->increments, static fn (string $id): bool => $id === $offerId));
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->increments;
    }
}
