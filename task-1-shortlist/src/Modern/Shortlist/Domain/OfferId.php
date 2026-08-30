<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Domain;

final readonly class OfferId
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Offer id must not be empty.');
        }

        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
