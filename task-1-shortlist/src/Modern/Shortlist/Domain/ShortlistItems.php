<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Domain;

final readonly class ShortlistItems implements \Countable
{
    /** @var list<OfferId> */
    private array $ids;

    public function __construct(OfferId ...$ids)
    {
        $unique = [];

        foreach ($ids as $id) {
            $unique[$id->value] = $id;
        }

        $this->ids = array_values($unique);
    }

    public function contains(OfferId $id): bool
    {
        return array_any($this->ids, fn (OfferId $known) => $known->equals($id));
    }

    public function add(OfferId $id): self
    {
        if ($this->contains($id)) {
            return $this;
        }

        return new self(...$this->ids, ...[$id]);
    }

    public function remove(OfferId $id): self
    {
        if (!$this->contains($id)) {
            return $this;
        }

        return new self(...array_filter($this->ids, fn (OfferId $known) => !$known->equals($id)));
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /** @return list<OfferId> */
    public function toOfferIds(): array
    {
        return $this->ids;
    }
}
