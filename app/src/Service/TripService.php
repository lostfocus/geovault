<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Responses\FullResponse;
use App\Entity\Database;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use App\Service\LocationService\DatabaseNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use phpGPX\Models\GpxFile;
use phpGPX\Models\Metadata;
use phpGPX\Models\Point;
use phpGPX\Models\Segment;
use phpGPX\Models\Track;
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

    public function getTripById(int $tripid, User $user): ?Trip
    {
        return $this->tripRepository->findOneBy(['id' => $tripid, 'user' => $user]);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \DateInvalidTimeZoneException
     * @throws DatabaseNotFoundException
     * @throws Exception
     */
    public function getGpx(Trip $trip): GpxFile
    {
        if (null === $trip->getLocationDatabase()->getReadToken()) {
            throw new \UnexpectedValueException('We need a proper database');
        }
        $result = $this->locationService->query(
            token: $trip->getLocationDatabase()->getReadToken(),
            startString: $trip->getStartUTC()?->format('Y-m-d\TH:i:sp'),
            endString: $trip->getEndUTC()?->format('Y-m-d\TH:i:sp'),
        );
        if (!$result instanceof FullResponse) {
            throw new \UnexpectedValueException('Response is not valid');
        }

        $utc = new \DateTimeZone('UTC');

        $gpx = new GpxFile();
        $gpx->metadata = new Metadata();
        $gpx->metadata->time = new \DateTime();
        $gpx->metadata->description = 'Trip exported from Geovault';

        $track = new Track();
        $track->name = $trip->getMode();
        $track->type = $trip->getMode();
        $track->source = 'Geovault';

        $segment = new Segment();

        foreach ($result->locations as $location) {
            if (
                !property_exists($location, 'geometry')
                || !property_exists($location, 'properties')
                || !property_exists($location->geometry, 'coordinates')
                || !property_exists($location->properties, 'altitude')
                || !property_exists($location->properties, 'timestamp')
            ) {
                continue;
            }
            $point = new Point(Point::TRACKPOINT);
            $point->latitude = $location->geometry->coordinates[1];
            $point->longitude = $location->geometry->coordinates[0];
            $point->elevation = $location->properties->altitude;
            $point->time = new \DateTime($location->properties->timestamp, $utc);
            $segment->points[] = $point;
        }
        $track->segments[] = $segment;
        $track->recalculateStats();
        $gpx->tracks[] = $track;

        return $gpx;
    }
}
