<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Support\InMemoryOfferCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\QueryBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the environment is wired: the kernel boots, Ecotone's buses and the Doctrine
 * entity manager are reachable, and the supplied test double works.
 * Delete this file once your own tests are in place.
 */
final class StarterSmokeTest extends KernelTestCase
{
    public function test_the_kernel_boots_with_ecotone_and_doctrine(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        self::assertInstanceOf(CommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(QueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(EntityManagerInterface::class, $container->get(EntityManagerInterface::class));
    }

    public function test_in_memory_catalog_serves_the_sample_offers(): void
    {
        $catalog = InMemoryOfferCatalog::withSampleOffers();

        self::assertNotNull($catalog->findByOfferId('off-1001'));
        self::assertNull($catalog->findByOfferId('off-9999'));
        self::assertCount(2, $catalog->findManyByOfferIds(['off-1001', 'off-1002', 'off-9999']));
    }
}
