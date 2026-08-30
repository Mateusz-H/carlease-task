<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Infrastructure;

use App\Modern\Shortlist\Application\Port\OfferPopularityCounterInterface;
use Psr\Log\LoggerInterface;

/**
 * Stand-in for the popularity counter. Production calls a remote service; this only
 * writes to the log, so a duplicated increment is visible instead of silent.
 */
final readonly class LoggingOfferPopularityCounter implements OfferPopularityCounterInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function increment(string $offerId): void
    {
        $this->logger->info('Offer popularity incremented', ['offerId' => $offerId]);
    }
}
