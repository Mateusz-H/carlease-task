<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Ui\ReadModel;

final readonly class OfferView
{
    public function __construct(
        public string $offerId,
        public string $brand,
        public string $model,
        public float $monthlyInstalment,
        public string $thumbnailUrl,
    ) {
    }
}
