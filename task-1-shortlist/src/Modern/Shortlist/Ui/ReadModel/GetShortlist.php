<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Ui\ReadModel;

final readonly class GetShortlist
{
    public function __construct(
        public string $visitorSessionId,
    ) {
    }
}
