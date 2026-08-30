<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Ui\ReadModel;

/** @implements \IteratorAggregate<int, OfferView> */
final readonly class OfferViews implements \IteratorAggregate, \Countable
{
    /** @var list<OfferView> */
    private array $views;

    public function __construct(OfferView ...$views)
    {
        $this->views = array_values($views);
    }

    public function contains(string $offerId): bool
    {
        return array_any($this->views, fn (OfferView $view) => $view->offerId === $offerId);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->views);
    }

    public function count(): int
    {
        return count($this->views);
    }
}
