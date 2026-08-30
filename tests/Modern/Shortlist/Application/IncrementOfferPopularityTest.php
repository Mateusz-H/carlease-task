<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Application;

use App\Modern\Shortlist\Application\Command\AddOfferToShortlist;
use App\Modern\Shortlist\Application\IncrementOfferPopularity;
use App\Modern\Shortlist\Application\Port\OfferPopularityCounterInterface;
use App\Modern\Shortlist\Domain\Shortlist;
use App\Tests\Support\RecordingPopularityCounter;
use Ecotone\Lite\EcotoneLite;
use Ecotone\Lite\Test\FlowTestSupport;
use Ecotone\Messaging\Channel\SimpleMessageChannelBuilder;
use Ecotone\Messaging\Config\ServiceConfiguration;
use Ecotone\Messaging\Endpoint\ExecutionPollingMetadata;
use PHPUnit\Framework\TestCase;

final class IncrementOfferPopularityTest extends TestCase
{
    private RecordingPopularityCounter $counter;
    private FlowTestSupport $ecotone;

    protected function setUp(): void
    {
        $this->counter = new RecordingPopularityCounter();
        $this->ecotone = EcotoneLite::bootstrapFlowTesting(
            [Shortlist::class, IncrementOfferPopularity::class],
            [
                OfferPopularityCounterInterface::class => $this->counter,
                new IncrementOfferPopularity($this->counter),
            ],
            ServiceConfiguration::createWithDefaults()->withExtensionObjects([
                SimpleMessageChannelBuilder::createQueueChannel('async'),
            ]),
        );
    }

    public function test_added_offer_increments_popularity_once(): void
    {
        $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->run('async', ExecutionPollingMetadata::createWithTestingSetup());

        self::assertSame(1, $this->counter->countFor('off-1'));
    }

    public function test_re_adding_same_offer_does_not_increment_again(): void
    {
        $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->run('async', ExecutionPollingMetadata::createWithTestingSetup(amountOfMessagesToHandle: 2));

        self::assertSame(1, $this->counter->countFor('off-1'));
    }

    public function test_each_distinct_offer_increments_its_own_counter(): void
    {
        $this->ecotone
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-1'))
            ->sendCommand(new AddOfferToShortlist('sess-1', 'off-2'))
            ->run('async', ExecutionPollingMetadata::createWithTestingSetup(amountOfMessagesToHandle: 2));

        self::assertSame(['off-1', 'off-2'], $this->counter->all());
    }
}
