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

    #[Route('/api/input', name: 'api_input', methods: ['POST'])]
    public function input(Request $request): JsonResponse
    {
        $token = $request->query->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }

        $input = $request->getContent();
        try {
            $inputContent = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(data: [
                'error' => 'invalid input',
            ], status: 400);
        }
        assert(is_array($inputContent));
        if (!array_key_exists('locations', $inputContent)) {
            return $this->json(data: [
                'error' => 'invalid input',
            ], status: 400);
        }

        try {
            $response = $this->locationService->input($token, $inputContent);
        } catch (LocationService\DatabaseNotFoundException) {
            return $this->json(data: [
                'error' => 'database not found',
            ], status: 400);
        } catch (LocationService\InvalidInputException) {
            return $this->json(data: [
                'error' => 'invalid input',
            ], status: 400);
        }

        return new JsonResponse($response);
    }

    #[Route('/api/query', name: 'api_query')]
    public function query(Request $request): JsonResponse
    {
        $token = $request->query->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }

        /** @var bool|float|int|string|null $tz */
        $tz = $request->query->get('tz', 'UTC');
        if (!is_string($tz)) {
            $tz = 'UTC';
        }
        $format = $request->query->get('format', 'full');
        if ('linestring' !== $format) {
            $format = 'full';
        }
        $date = $request->query->get('date');
        if (!is_string($date)) {
            $date = null;
        }
        $start = $request->query->get('start');
        if (!is_string($start)) {
            $start = null;
        }
        $end = $request->query->get('end');
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
        $token = $request->query->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }
        /** @var bool|float|int|string|null $tz */
        $tz = $request->query->get('tz', 'UTC');
        if (!is_string($tz)) {
            $tz = 'UTC';
        }
        $before = $request->query->get('before');
        if (!is_string($before)) {
            $before = null;
        }
        $geocodeString = $request->query->get('geocode');
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
        $token = $request->query->get('token');
        if (!is_string($token)) {
            return $this->json(data: [
                'error' => 'no token provided',
            ], status: 400);
        }

        $input = $request->query->get('input');
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
