<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Responses\FullResponse;
use App\Entity\Database;
use App\Entity\Trip;
use App\Repository\TripRepository;
use App\Service\LocationService\DatabaseNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Quartz\Exception;

final readonly class TripService
{
    public function __construct(
        private LocationService $locationService,
        private TripRepository $tripRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Trip[]|null
     *
     * @throws DatabaseNotFoundException
     * @throws Exception
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function findDayTrips(Database $database, \DateTimeInterface $day): ?array
    {
        if (null === $database->getReadToken()) {
            return null;
        }
        $result = $this->locationService->query(
            $database->getReadToken(),
            $day->format('Y-m-d'),
        );
        if (!$result instanceof FullResponse) {
            return null;
        }
        /** @var array<string, Trip> $trips */
        $trips = [];

        $utc = new \DateTimeZone('UTC');

        foreach ($result->locations as $location) {
            if (!property_exists($location, 'properties')) {
                continue;
            }

            $properties = $location->properties;

            if (
                $properties instanceof \stdClass
                && property_exists($properties, 'trip_id')
            ) {
                if (array_key_exists($properties->trip_id, $trips)) {
                    $trip = $trips[$properties->trip_id];
                } else {
                    $trip = $this->tripRepository->findOneBy(['startString' => $properties->trip_id]);
                }
                if (!$trip instanceof Trip) {
                    $start = new \DateTimeImmutable($properties->trip_id, $utc);
                    $trip = new Trip()
                        ->setStartString($properties->trip_id)
                        ->setStartUTC($start)
                        ->setContent([])
                        ->setLocationDatabase($database)
                        ->setUser($database->getUser());
                    $this->entityManager->persist($trip);
                }
                $trips[$properties->trip_id] = $trip;
            }
            if (
                $properties instanceof \stdClass
                && property_exists($properties, 'type')
                && 'trip' === $properties->type
                && property_exists($properties, 'start')
                && is_string($properties->start)
                && property_exists($properties, 'end')
                && is_string($properties->end)
            ) {
                if (array_key_exists($properties->type, $trips)) {
                    $trip = $trips[$properties->start];
                } else {
                    $trip = $this->tripRepository->findOneBy(['startString' => $properties->start]);
                }
                if (!$trip instanceof Trip) {
                    $start = new \DateTimeImmutable($properties->start, $utc);
                    $trip = new Trip()
                        ->setStartString($properties->start)
                        ->setStartUTC($start)
                        ->setContent([])
                        ->setLocationDatabase($database)
                        ->setUser($database->getUser());
                    $this->entityManager->persist($trip);
                }
                $end = new \DateTimeImmutable($properties->end, $utc);
                $trip->setEndUTC($end);

                if (
                    property_exists($properties, 'mode')
                    && is_string($properties->mode)
                ) {
                    $trip->setMode($properties->mode);
                }
                if (property_exists($properties, 'distance') && is_float($properties->distance)) {
                    $trip->setDistance($properties->distance);
                }
                if (property_exists($properties, 'duration') && is_float($properties->duration)) {
                    $trip->setDuration($properties->duration);
                }
                if (property_exists($properties, 'steps') && is_int($properties->steps)) {
                    $trip->setSteps($properties->steps);
                }
            }
        }
        $this->entityManager->flush();

        return $trips;
    }
}
