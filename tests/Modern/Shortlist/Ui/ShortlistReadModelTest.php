<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Ui;

use App\Modern\Shortlist\Application\Command\AddOfferToShortlist;
use App\Modern\Shortlist\Ui\ReadModel\GetShortlist;
use App\Modern\Shortlist\Ui\ReadModel\OfferView;
use App\Modern\Shortlist\Ui\ReadModel\OfferViews;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\QueryBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ShortlistReadModelTest extends KernelTestCase
{
    private CommandBus $commandBus;
    private QueryBus $queryBus;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->commandBus = $container->get(CommandBus::class);
        $this->queryBus = $container->get(QueryBus::class);
    }

    public function test_unknown_visitor_gets_empty_shortlist(): void
    {
        $views = $this->queryBus->send(new GetShortlist('sess-' . Uuid::v4()->toRfc4122()));

        self::assertInstanceOf(OfferViews::class, $views);
        self::assertCount(0, $views);
    }

    public function test_shortlist_is_enriched_from_catalog_and_vanished_offers_are_skipped(): void
    {
        $sessionId = 'sess-' . Uuid::v4()->toRfc4122();

        $this->commandBus->send(new AddOfferToShortlist($sessionId, 'off-1002'));
        $this->commandBus->send(new AddOfferToShortlist($sessionId, 'off-gone'));
        $this->commandBus->send(new AddOfferToShortlist($sessionId, 'off-1001'));

        $views = $this->queryBus->send(new GetShortlist($sessionId));

        self::assertInstanceOf(OfferViews::class, $views);
        self::assertSame(
            ['off-1002', 'off-1001'],
            array_map(fn (OfferView $view) => $view->offerId, iterator_to_array($views)),
        );

        [$first] = iterator_to_array($views);
        self::assertSame('Skoda', $first->brand);
        self::assertSame('Octavia', $first->model);
        self::assertSame(1449.50, $first->monthlyInstalment);
        self::assertSame('/images/offer-placeholder.svg', $first->thumbnailUrl);

        self::assertTrue($views->contains('off-1001'));
        self::assertFalse($views->contains('off-gone'));
    }
}
