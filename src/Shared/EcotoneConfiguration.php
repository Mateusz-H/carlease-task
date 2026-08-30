<?php

declare(strict_types=1);

namespace App\Shared;

use Ecotone\Dbal\Configuration\DbalConfiguration;
use Ecotone\Dbal\DbalBackedMessageChannelBuilder;
use Ecotone\Messaging\Attribute\ServiceContext;
use Ecotone\SymfonyBundle\Config\SymfonyConnectionReference;

/**
 * Stores aggregates through Doctrine ORM using the connection Symfony already configures.
 */
final class EcotoneConfiguration
{
    #[ServiceContext]
    public function dbalConfiguration(): DbalConfiguration
    {
        return DbalConfiguration::createWithDefaults()
            ->withDoctrineORMRepositories(true);
    }

    #[ServiceContext]
    public function managerRegistry(): SymfonyConnectionReference
    {
        return SymfonyConnectionReference::defaultManagerRegistry('default');
    }

    /**
     * Queue for handlers marked #[Asynchronous('async')]. Messages wait in the database
     * until a consumer picks them up:
     *
     *   php bin/console ecotone:run async --finishWhenNoMessages=true
     *
     * The "=true" is not optional. The flag takes an optional value, so passing it bare
     * leaves the consumer running forever instead of stopping on an empty queue.
 *
 * The same trap exists in tests: EcotoneLite's run('async') polls forever unless it is
 * given ExecutionPollingMetadata::createWithTestingSetup(). PHPUnit simply never returns.
 * And the fix has its own edge: createWithTestingSetup() consumes ONE message by
 * default, so a test that enqueues several and asserts afterwards can pass while most
 * of them are still sitting in the channel. Pass the count you mean.
     */
    #[ServiceContext]
    public function asyncChannel(): DbalBackedMessageChannelBuilder
    {
        return DbalBackedMessageChannelBuilder::create('async');
    }
}
