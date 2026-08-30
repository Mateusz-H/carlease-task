<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Domain;

use App\Modern\Shortlist\Domain\OfferId;
use App\Modern\Shortlist\Domain\ShortlistItems;
use PHPUnit\Framework\TestCase;

final class ShortlistItemsTest extends TestCase
{
    public function test_deduplicates_on_construction(): void
    {
        $items = new ShortlistItems(new OfferId('off-1'), new OfferId('off-1'), new OfferId('off-2'));

        self::assertCount(2, $items);
    }

    public function test_add_returns_grown_copy_and_keeps_original(): void
    {
        $items = new ShortlistItems(new OfferId('off-1'));
        $grown = $items->add(new OfferId('off-2'));

        self::assertCount(1, $items);
        self::assertCount(2, $grown);
        self::assertTrue($grown->contains(new OfferId('off-2')));
    }

    public function test_add_of_present_id_is_noop(): void
    {
        $items = new ShortlistItems(new OfferId('off-1'));

        self::assertSame($items, $items->add(new OfferId('off-1')));
    }

    public function test_remove_drops_id(): void
    {
        $items = new ShortlistItems(new OfferId('off-1'), new OfferId('off-2'));
        $shrunk = $items->remove(new OfferId('off-1'));

        self::assertCount(1, $shrunk);
        self::assertFalse($shrunk->contains(new OfferId('off-1')));
    }

    public function test_remove_of_absent_id_is_noop(): void
    {
        $items = new ShortlistItems(new OfferId('off-1'));

        self::assertSame($items, $items->remove(new OfferId('off-9')));
    }

    public function test_to_offer_ids_preserves_insertion_order(): void
    {
        $items = new ShortlistItems(new OfferId('off-2'), new OfferId('off-1'));

        self::assertSame(
            ['off-2', 'off-1'],
            array_map(fn (OfferId $id) => $id->value, $items->toOfferIds()),
        );
    }
}
