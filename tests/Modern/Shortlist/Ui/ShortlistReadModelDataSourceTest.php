<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Ui;

use App\Modern\Shortlist\Ui\ReadModel\GetShortlist;
use App\Modern\Shortlist\Ui\ReadModel\OfferView;
use App\Modern\Shortlist\Ui\ReadModel\ShortlistReadModel;
use App\Tests\Support\InMemoryOfferCatalog;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Proves the two-sources-of-truth split directly, not through the schema: the database
 * may hand the read model nothing but saved offer ids, and every displayed field must
 * come from the catalog index.
 */
final class ShortlistReadModelDataSourceTest extends TestCase
{
    public function test_database_contributes_only_ids_and_catalog_all_offer_data(): void
    {
        $executedQueries = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$executedQueries): string {
                $executedQueries[] = [$sql, $params];

                return '["off-1002","off-gone","off-1001"]';
            });

        $catalog = InMemoryOfferCatalog::withSampleOffers();

        $views = (new ShortlistReadModel($connection, $catalog))
            ->getShortlist(new GetShortlist('sess-data-source'));

        self::assertSame(
            [['SELECT offer_ids FROM shortlist WHERE visitor_session_id = ?', ['sess-data-source']]],
            $executedQueries,
            'The only SQL the read path runs selects offer ids from the shortlist table.',
        );

        $expected = array_map(
            fn (string $id) => $catalog->findByOfferId($id),
            ['off-1002', 'off-1001'],
        );
        self::assertSame(
            $expected,
            array_map(
                fn (OfferView $view) => [
                    'offerId' => $view->offerId,
                    'brand' => $view->brand,
                    'model' => $view->model,
                    'monthlyInstalment' => $view->monthlyInstalment,
                    'thumbnailUrl' => $view->thumbnailUrl,
                ],
                iterator_to_array($views->offers),
            ),
            'Every displayed field is the catalog document verbatim.',
        );

        self::assertSame(
            ['off-gone'],
            $views->unavailableOfferIds,
            'An offer absent from the index is reported as unavailable, not silently dropped.',
        );
    }
}
