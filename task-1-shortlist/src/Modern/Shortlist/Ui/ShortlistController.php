<?php

declare(strict_types=1);

namespace App\Modern\Shortlist\Ui;

use App\Modern\Shortlist\Application\Command\AddOfferToShortlist;
use App\Modern\Shortlist\Application\Command\RemoveOfferFromShortlist;
use App\Modern\Shortlist\Application\Port\OfferCatalogInterface;
use App\Modern\Shortlist\Domain\Shortlist;
use App\Modern\Shortlist\Domain\ShortlistCapacityExceeded;
use App\Modern\Shortlist\Ui\ReadModel\GetShortlist;
use App\Modern\Shortlist\Ui\ReadModel\OfferView;
use App\Modern\Shortlist\Ui\ReadModel\OfferViews;
use Ecotone\Modelling\AggregateNotFoundException;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ShortlistController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly OfferCatalogInterface $catalog,
    ) {
    }

    #[Route('/', name: 'shortlist_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $shortlist = $this->queryBus->send(new GetShortlist($this->visitorSessionId($request)));

        $available = new OfferViews(...array_map(
            fn (array $document) => new OfferView(
                offerId: $document['offerId'],
                brand: $document['brand'],
                model: $document['model'],
                monthlyInstalment: (float) $document['monthlyInstalment'],
                thumbnailUrl: $document['thumbnailUrl'],
            ),
            array_values($this->catalog->findAvailableOffers()),
        ));

        return $this->render('shortlist/index.html.twig', [
            'available' => $available,
            'shortlist' => $shortlist,
            'capacity' => Shortlist::CAPACITY,
        ]);
    }

    #[Route('/shortlist/add', name: 'shortlist_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $this->assertCsrfToken($request);

        try {
            $this->commandBus->send(new AddOfferToShortlist(
                $this->visitorSessionId($request),
                $this->offerIdFrom($request),
            ));
        } catch (ShortlistCapacityExceeded $exception) {
            $this->addFlash('error', sprintf(
                'Schowek mieści najwyżej %d ofert. Usuń którąś, aby dodać kolejną.',
                $exception->capacity,
            ));
        }

        return $this->redirectToRoute('shortlist_index');
    }

    #[Route('/shortlist/remove', name: 'shortlist_remove', methods: ['POST'])]
    public function remove(Request $request): Response
    {
        $this->assertCsrfToken($request);

        try {
            $this->commandBus->send(new RemoveOfferFromShortlist(
                $this->visitorSessionId($request),
                $this->offerIdFrom($request),
            ));
        } catch (AggregateNotFoundException) {
        }

        return $this->redirectToRoute('shortlist_index');
    }

    private function assertCsrfToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('shortlist', $request->request->getString('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }

    private function offerIdFrom(Request $request): string
    {
        $offerId = trim($request->request->getString('offerId'));

        if ($offerId === '') {
            throw new BadRequestHttpException('Missing offerId.');
        }

        return $offerId;
    }

    private function visitorSessionId(Request $request): string
    {
        $session = $request->getSession();
        $id = $session->get('visitor_session_id');

        if (!is_string($id) || $id === '') {
            $id = 'sess-' . Uuid::v4()->toRfc4122();
            $session->set('visitor_session_id', $id);
        }

        return $id;
    }
}
