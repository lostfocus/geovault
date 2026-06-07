<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Trip;
use App\Entity\User;
use App\Service\TripService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class TripController extends AbstractController
{
    public function __construct(
        private readonly TripService $tripService,
    ) {
    }

    #[Route('/trip/{tripid}.gpx', name: 'tripgpx')]
    public function index(int $tripid): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $trip = $this->tripService->getTripById($tripid, $user);
        if (!$trip instanceof Trip) {
            throw new AccessDeniedHttpException();
        }

        $gpx = $this->tripService->getGpx($trip);

        $content = $gpx->toXML()->saveXML();
        if (false === $content) {
            throw new AccessDeniedHttpException();
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => sprintf('attachment; filename=trip-%s.gpx', $tripid),
        ]);
    }
}
