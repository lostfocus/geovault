<?php

namespace App\Controller;

use App\Service\LocationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController extends AbstractController
{
    public function __construct(
        private readonly LocationService $locationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/query', name: 'api_query')]
    public function query(Request $request): JsonResponse
    {
        $token = $request->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }

        $tz = $request->get('tz', 'UTC');
        if (!is_string($tz)) {
            $tz = 'UTC';
        }
        $format = $request->get('format', 'full');
        if ('linestring' !== $format) {
            $format = 'full';
        }
        $date = $request->get('date');
        if (!is_string($date)) {
            $date = null;
        }
        $start = $request->get('start');
        if (!is_string($start)) {
            $start = null;
        }
        $end = $request->get('end');
        if (!is_string($end)) {
            $end = null;
        }

        try {
            $response = $this->locationService->query(token: $token, dateString: $date, startString: $start, endString: $end, tz: $tz, format: $format);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->json(data: [
                'error' => $e->getMessage(),
            ], status: 500);
        }

        return $this->json($response);
    }

    #[Route('/api/last', name: 'api_last')]
    public function index(
        Request $request,
    ): JsonResponse {
        $token = $request->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }

        $tz = $request->get('tz', 'UTC');
        if (!is_string($tz)) {
            $tz = 'UTC';
        }
        $before = $request->get('before');
        if (!is_string($before)) {
            $before = null;
        }
        $geocodeString = $request->get('geocode');
        $geocode = ('true' === $geocodeString);

        try {
            $response = $this->locationService->getLast(token: $token, before: $before, tz: $tz, geocode: $geocode);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->json(data: [
                'error' => $e->getMessage(),
            ], status: 500);
        }

        return $this->json($response);
    }

    #[Route('/api/find-from-localtime', name: 'api_from_localtime')]
    public function fromLocalTime(Request $request): JsonResponse
    {
        $token = $request->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }

        $input = $request->get('input');
        if (!is_string($input)) {
            return $this->json(data: [
                'error' => 'Invalid date provided',
            ], status: 400);
        }
        try {
            $response = $this->locationService->getFromLocalTime($token, $input);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->json(data: [
                'error' => $e->getMessage(),
            ], status: 500);
        }

        return $this->json($response);
    }
}
