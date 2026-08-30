<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Domain;

final class ShortlistCapacityExceeded extends \DomainException
{
    public function __construct(
        public readonly int $capacity,
        public readonly int $currentCount,
    ) {
        parent::__construct(sprintf('Shortlist capacity %d exceeded, current count %d.', $capacity, $currentCount));
    }
}
