<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Domain;

use App\Modern\Shortlist\Application\Command\AddOfferToShortlist;
use App\Modern\Shortlist\Application\Command\RemoveOfferFromShortlist;
use App\Modern\Shortlist\Domain\OfferAddedToShortlist;
use App\Modern\Shortlist\Domain\Shortlist;
use App\Modern\Shortlist\Domain\ShortlistCapacityExceeded;
use Ecotone\Lite\EcotoneLite;
use Ecotone\Lite\Test\FlowTestSupport;
use PHPUnit\Framework\TestCase;

final class ShortlistTest extends TestCase
{
    private FlowTestSupport $ecotone;

    protected function setUp(): void
    {
        $this->ecotone = EcotoneLite::bootstrapFlowTesting([Shortlist::class]);
    }

    public function test_first_add_creates_shortlist_and_publishes_event(): void
    {
        $events = $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->getRecordedEvents();

        self::assertEquals([new OfferAddedToShortlist('sess-1', 'off-1')], $events);
    }

    public function test_add_to_existing_shortlist_publishes_event(): void
    {
        $events = $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-2'))
            ->getRecordedEvents();

        self::assertCount(2, $events);
    }

    public function test_re_adding_same_offer_changes_nothing_and_publishes_nothing(): void
    {
        $events = $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->getRecordedEvents();

        self::assertCount(1, $events);
    }

    public function test_removing_absent_offer_is_a_no_op(): void
    {
        $events = $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->sendCommand(new RemoveOfferFromShortlist('sess-1', 'off-9'))
            ->getRecordedEvents();

        self::assertEquals([new OfferAddedToShortlist('sess-1', 'off-1')], $events);
    }

    public function test_removed_offer_can_be_added_again_with_new_event(): void
    {
        $events = $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->sendCommand(new RemoveOfferFromShortlist('sess-1', 'off-1'))
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->getRecordedEvents();

        self::assertCount(2, $events);
    }

    public function test_eleventh_offer_exceeds_capacity(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->ecotone->sendCommand(new AddOfferToShortlist('sess-1', "off-$i"));
        }

        try {
            $this->ecotone->sendCommand(new AddOfferToShortlist('sess-1', 'off-11'));
            self::fail('Expected ShortlistCapacityExceeded');
        } catch (ShortlistCapacityExceeded $exception) {
            self::assertSame(10, $exception->capacity);
            self::assertSame(10, $exception->currentCount);
        }
    }
}
