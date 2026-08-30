<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Application;

use App\Modern\Shortlist\Domain\OfferAddedToShortlist;
use App\Tests\Support\RecordingPopularityCounter;
use Ecotone\Messaging\Config\ConfiguredMessagingSystem;
use Ecotone\Messaging\Endpoint\ExecutionPollingMetadata;
use Ecotone\Modelling\EventBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OfferPopularityDeduplicationTest extends KernelTestCase
{
    private EventBus $eventBus;
    private RecordingPopularityCounter $counter;
    private ConfiguredMessagingSystem $messaging;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->eventBus = $container->get(EventBus::class);
        $this->counter = $container->get(RecordingPopularityCounter::class);
        $this->messaging = $container->get(ConfiguredMessagingSystem::class);

        $this->drainAsyncChannel();
    }

    public function test_redelivered_message_increments_popularity_only_once(): void
    {
        $offerId = 'off-' . Uuid::v4()->toRfc4122();
        $event = new OfferAddedToShortlist('sess-dedup', $offerId);
        $messageId = Uuid::v4()->toRfc4122();

        $this->eventBus->publish($event, ['id' => $messageId]);
        $this->eventBus->publish($event, ['id' => $messageId]);

        $this->drainAsyncChannel();

        self::assertSame(1, $this->counter->countFor($offerId));
    }

    public function test_distinct_messages_for_the_same_offer_both_count(): void
    {
        $offerId = 'off-' . Uuid::v4()->toRfc4122();
        $event = new OfferAddedToShortlist('sess-dedup', $offerId);

        $this->eventBus->publish($event, ['id' => Uuid::v4()->toRfc4122()]);
        $this->eventBus->publish($event, ['id' => Uuid::v4()->toRfc4122()]);

        $this->drainAsyncChannel();

        self::assertSame(2, $this->counter->countFor($offerId));
    }

    private function drainAsyncChannel(): void
    {
        $this->messaging->run('async', ExecutionPollingMetadata::createWithFinishWhenNoMessages());
    }
}
