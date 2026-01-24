<?php

namespace App\Controller;

use App\Entity\Database;
use App\Entity\User;
use App\Service\DatabaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MapController extends AbstractController
{
    public function __construct(
        private readonly DatabaseService $databaseService,
    ) {
    }

    #[Route('/map/{mapid}', name: 'map')]
    public function index(int $mapid): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_home');
        }
        $database = $this->databaseService->getDatabaseById($mapid, $user);
        if (!$database instanceof Database) {
            return $this->redirectToRoute('app_home');
        }

        $utc = new \DateTimeZone('UTC');
        $start = new \DateTimeImmutable()->setTime(0, 0)->setTimezone($utc);

        return $this->render('map/index.html.twig', [
            'database' => $database,
            'date' => $start,
        ]);
    }
}
