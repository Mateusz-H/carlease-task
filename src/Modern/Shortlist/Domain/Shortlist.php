<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Domain;

use App\Modern\Shortlist\Application\Command\AddOfferToShortlist;
use App\Modern\Shortlist\Application\Command\RemoveOfferFromShortlist;
use Doctrine\ORM\Mapping as ORM;
use Ecotone\Modelling\Attribute\Aggregate;
use Ecotone\Modelling\Attribute\CommandHandler;
use Ecotone\Modelling\Attribute\Identifier;
use Ecotone\Modelling\WithEvents;

#[Aggregate]
#[ORM\Entity]
#[ORM\Table(name: 'shortlist')]
class Shortlist
{
    use WithEvents;

    public const CAPACITY = 10;

    #[Identifier]
    #[ORM\Id]
    #[ORM\Column(name: 'visitor_session_id', length: 64)]
    private string $visitorSessionId;

    /** @var list<string> */
    #[ORM\Column(name: 'offer_ids', type: 'json')]
    private array $offerIds;

    private function __construct(string $visitorSessionId)
    {
        $this->visitorSessionId = $visitorSessionId;
        $this->offerIds = [];
    }

    #[CommandHandler]
    public static function start(AddOfferToShortlist $command): self
    {
        $shortlist = new self($command->visitorSessionId);
        $shortlist->addOffer($command);

        return $shortlist;
    }

    #[CommandHandler]
    public function addOffer(AddOfferToShortlist $command): void
    {
        $offerId = new OfferId($command->offerId);
        $items = $this->items();

        if ($items->contains($offerId)) {
            return;
        }

        if (count($items) >= self::CAPACITY) {
            throw new ShortlistCapacityExceeded(self::CAPACITY, count($items));
        }

        $this->storeItems($items->add($offerId));
        $this->recordThat(new OfferAddedToShortlist($this->visitorSessionId, $offerId->value));
    }

    #[CommandHandler]
    public function removeOffer(RemoveOfferFromShortlist $command): void
    {
        $this->storeItems($this->items()->remove(new OfferId($command->offerId)));
    }

    private function items(): ShortlistItems
    {
        return new ShortlistItems(...array_map(fn (string $id) => new OfferId($id), $this->offerIds));
    }

    private function storeItems(ShortlistItems $items): void
    {
        $this->offerIds = array_map(fn (OfferId $id) => $id->value, $items->toOfferIds());
    }
}
