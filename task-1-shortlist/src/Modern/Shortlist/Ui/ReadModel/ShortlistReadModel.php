<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Ui\ReadModel;

use App\Modern\Shortlist\Application\Port\OfferCatalogInterface;
use Doctrine\DBAL\Connection;
use Ecotone\Modelling\Attribute\QueryHandler;

final readonly class ShortlistReadModel
{
    public function __construct(
        private Connection $connection,
        private OfferCatalogInterface $catalog,
    ) {
    }

    /**
     * Read path bypasses the aggregate on purpose: the database contributes only WHICH
     * offers were saved, the catalog index is the only source of what a car IS, asked
     * once per view. Offers gone from the index still occupy capacity slots, so they are
     * returned as unavailable ids instead of vanishing — a vanished offer must never
     * break the view, but hiding it would make the capacity counter lie.
     */
    #[QueryHandler]
    public function getShortlist(GetShortlist $query): ShortlistView
    {
        $json = $this->connection->fetchOne(
            'SELECT offer_ids FROM shortlist WHERE visitor_session_id = ?',
            [$query->visitorSessionId],
        );

        if ($json === false) {
            return new ShortlistView();
        }

        /** @var list<string> $offerIds */
        $offerIds = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
        $documents = $this->catalog->findManyByOfferIds($offerIds);

        $views = [];
        $unavailable = [];

        foreach ($offerIds as $offerId) {
            if (!isset($documents[$offerId])) {
                $unavailable[] = $offerId;
                continue;
            }

            $document = $documents[$offerId];
            $views[] = new OfferView(
                offerId: $document['offerId'],
                brand: $document['brand'],
                model: $document['model'],
                monthlyInstalment: (float) $document['monthlyInstalment'],
                thumbnailUrl: $document['thumbnailUrl'],
            );
        }

        return new ShortlistView(new OfferViews(...$views), $unavailable);
    }
}
