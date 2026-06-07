<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Responses\InputResponse;
use App\Dto\Responses\QuartzResponse;
use App\Dto\Responses\QuartzTimezone;
use App\Dto\Responses\QueryResponse;
use App\Entity\Database;
use App\Entity\Location;
use App\Entity\Trip;
use App\Repository\DatabaseRepository;
use App\Repository\LocationRepository;
use App\Repository\TripRepository;
use App\Service\LocationService\DatabaseNotFoundException;
use App\Service\LocationService\InvalidInputException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Quartz\Exception;

readonly class LocationService
{
    public function __construct(
        private QuartzService $quartz,
        private LoggerInterface $logger,
        private DatabaseRepository $databaseRepository,
        private LocationRepository $locationRepository,
        private TripRepository $tripRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateInvalidOperationException
     * @throws DatabaseNotFoundException
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function getLast(
        string $token,
        ?string $before = null,
        string $tz = 'UTC',
        bool $geocode = false,
    ): QuartzResponse {
        $database = $this->databaseRepository->findOneBy(['readToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNotFoundException('invalid token');
        }

        $dateTime = $this->tryToGuessDate($before, $tz);

        $response = $this->quartz->getLast($database->getSlug(), $dateTime);
        if (null === $response->data) {
            $latestLocation = $this->locationRepository->findLatestBefore($database, $dateTime);
            if (null === $latestLocation) {
                return $response;
            }
            try {
                $data = json_decode(
                    json_encode($latestLocation->getContent(), JSON_THROW_ON_ERROR),
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $quartzTimezone = null;
                if (
                    null !== $latestLocation->getLatitude()
                    && null !== $latestLocation->getLongitude()
                    && null !== $latestLocation->getTimestampUTC()
                ) {
                    $timezoneResult = $this->quartz->timezoneForLocation(
                        $latestLocation->getLatitude(),
                        $latestLocation->getLongitude(),
                        $latestLocation->getTimestampUTC()->format('c')
                    );
                    $quartzTimezone = new QuartzTimezone(
                        offset: $timezoneResult->date->format('P'),
                        seconds: (int) $timezoneResult->date->format('Z'),
                        localtime: $timezoneResult->date->format('c'),
                        name: $timezoneResult->name,
                    );
                }
            } catch (\JsonException $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);

                return $response;
            }
            assert($data instanceof \stdClass);

            return new QuartzResponse(
                data: $data,
                timezone: $quartzTimezone,
            );
        }

        return $response;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \DateInvalidTimeZoneException
     */
    private function tryToGuessDate(?string $dateString, string $tz): \DateTime
    {
        $timezone = new \DateTimeZone($tz);
        if (null === $dateString) {
            return new \DateTime('now', $timezone);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateString)) {
            // If the input date is given in YYYY-mm-dd HH:mm:ss format, interpret it in the timezone given
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateString, $timezone);
            if (false === $date) {
                throw new \DateMalformedStringException();
            }

            return $date;
        }
        // Otherwise, parse the string and use the timezone in the input
        $date = new \DateTime($dateString);
        $date->setTimeZone($timezone);

        return $date;
    }

    /**
     * @throws DatabaseNotFoundException
     * @throws \DateInvalidOperationException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function getFromLocalTime(string $token, string $input): QuartzResponse
    {
        $database = $this->databaseRepository->findOneBy(['readToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNotFoundException('invalid token');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $input)) {
            $date = $input;
        } else {
            throw new \UnexpectedValueException('Invalid date string');
        }

        return $this->quartz->getFromLocalTime($database->getSlug(), $date);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws Exception
     * @throws DatabaseNotFoundException
     */
    public function query(
        string $token,
        ?string $dateString = null,
        ?string $startString = null,
        ?string $endString = null,
        string $tz = 'UTC',
        string $format = 'full',
    ): QueryResponse {
        $database = $this->databaseRepository->findOneBy(['readToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNotFoundException('invalid token');
        }

        $timezone = new \DateTimeZone($tz);
        if (null !== $dateString) {
            $start = \DateTime::createFromFormat('Y-m-d H:i:s', $dateString.' 00:00:00', $timezone);
            $end = \DateTime::createFromFormat('Y-m-d H:i:s', $dateString.' 23:59:59', $timezone);
            if (false === $start || false === $end) {
                throw new \DateMalformedStringException();
            }
        } elseif (null !== $startString && null !== $endString) {
            $start = new \DateTime($startString, $timezone);
            $end = new \DateTime($endString, $timezone);
        } else {
            throw new \UnexpectedValueException('no date provided');
        }

        return $this->quartz->query($database->getSlug(), $start, $end, $format);
    }

    /**
     * @param array<string, array<string, mixed>> $inputContent
     *
     * @throws DatabaseNotFoundException
     * @throws InvalidInputException
     * @throws \DateMalformedStringException
     */
    public function input(string $token, array $inputContent): InputResponse
    {
        $database = $this->databaseRepository->findOneBy(['writeToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNotFoundException('invalid token');
        }
        if (
            !array_key_exists('locations', $inputContent)
            /*
             * @phpstan-ignore function.alreadyNarrowedType
             */
            || !is_array($inputContent['locations'])
        ) {
            throw new InvalidInputException('locations array is missing');
        }

        $utc = new \DateTimeZone('UTC');

        $num = 0;
        $lastLoc = null;

        /** @var array<string, Trip> $trips */
        $trips = [];

        if (
            array_key_exists('trip', $inputContent)
            /*
             * @phpstan-ignore function.alreadyNarrowedType
             */
            && is_array($inputContent['trip'])
            && array_key_exists('start', $inputContent['trip'])
            && is_string($inputContent['trip']['start'])
            && array_key_exists('mode', $inputContent['trip'])
            && is_string($inputContent['trip']['mode'])
        ) {
            $trip = $this->tripRepository->findOneBy(['startString' => $inputContent['trip']['start']]);
            if (!$trip instanceof Trip) {
                $startUtc = new \DateTimeImmutable($inputContent['trip']['start'], $utc);
                $trip = new Trip()
                    ->setStartString($inputContent['trip']['start'])
                    ->setStartUTC($startUtc)
                    ->setMode($inputContent['trip']['mode'])
                    ->setLocationDatabase($database)
                    ->setContent($inputContent['trip'])
                    ->setUser($database->getUser());
                $this->entityManager->persist($trip);
            }

            if (array_key_exists('distance', $inputContent['trip']) && is_int($inputContent['trip']['distance'])) {
                $trip->setDistance($inputContent['trip']['distance']);
            }
            $trips[$inputContent['trip']['start']] = $trip;
        }

        foreach ($inputContent['locations'] as $loc) {
            if (
                !is_array($loc)
                || !array_key_exists('properties', $loc)
                || !is_array($loc['properties'])
                || !array_key_exists('timestamp', $loc['properties'])
                || !is_string($loc['properties']['timestamp'])
            ) {
                continue;
            }
            try {
                $date = null;
                if (preg_match('/^\d+\.\d+$/', $loc['properties']['timestamp'])) {
                    $date = \DateTimeImmutable::createFromFormat('U.u', $loc['properties']['timestamp']);
                } elseif (preg_match('/^\d+$/', $loc['properties']['timestamp'])) {
                    $date = \DateTimeImmutable::createFromFormat('U', $loc['properties']['timestamp']);
                } else {
                    $date = new \DateTimeImmutable($loc['properties']['timestamp']);
                }

                if (!$date instanceof \DateTimeImmutable) {
                    continue;
                }

                $shouldAdd = true;
                $date = $date->setTimezone($utc);

                // Check the database if we have a location at this timestamp already
                $location = $this->locationRepository->findOneBy(['timestampUTC' => $date]);
                if ($location instanceof Location) {
                    $shouldAdd = false;
                }

                if (
                    array_key_exists('geometry', $loc)
                    && is_array($loc['geometry'])
                    && array_key_exists('coordinates', $loc['geometry'])
                    && is_array($loc['geometry']['coordinates'])
                    && count($loc['geometry']['coordinates']) > 1
                    && 0 === $loc['geometry']['coordinates'][0]
                    && 0 === $loc['geometry']['coordinates'][1]
                ) {
                    $shouldAdd = false;
                }

                if ($shouldAdd) {
                    $trip = null;
                    if (
                        array_key_exists('trip_id', $loc['properties'])
                        && is_string($loc['properties']['trip_id'])
                    ) {
                        if (array_key_exists($loc['properties']['trip_id'], $trips)) {
                            $trip = $trips[$loc['properties']['trip_id']];
                        } else {
                            $trip = $this->tripRepository->findOneBy(['startString' => $loc['properties']['trip_id']]);
                            if ($trip instanceof Trip) {
                                $trips[$loc['properties']['trip_id']] = $trip;
                            } else {
                                $trip = null;
                            }
                        }
                    }

                    if (
                        array_key_exists('type', $loc['properties'])
                        && 'trip' === $loc['properties']['type']
                        && array_key_exists('start', $loc['properties'])
                        && is_string($loc['properties']['start'])
                    ) {
                        if (array_key_exists($loc['properties']['start'], $trips)) {
                            $trip = $trips[$loc['properties']['start']];
                        } else {
                            $trip = $this->tripRepository->findOneBy(['startString' => $loc['properties']['start']]);
                        }
                        if ($trip instanceof Trip) {
                            if (array_key_exists('distance', $loc['properties']) && is_float(
                                $loc['properties']['distance']
                            )) {
                                $trip->setDistance($loc['properties']['distance']);
                            }
                            if (array_key_exists('duration', $loc['properties']) && is_float(
                                $loc['properties']['duration']
                            )) {
                                $trip->setDuration($loc['properties']['duration']);
                            }
                            if (array_key_exists('steps', $loc['properties']) && is_int($loc['properties']['steps'])) {
                                $trip->setSteps($loc['properties']['steps']);
                            }
                            if (array_key_exists('end', $loc['properties']) && is_string($loc['properties']['end'])) {
                                $endUTC = new \DateTimeImmutable($loc['properties']['end'], $utc);
                                $trip->setEndUTC($endUTC);
                            }
                        }
                    }

                    ++$num;
                    $this->quartz->write($database->getSlug(), $date, $loc);
                    $location = new Location()
                        ->setTimestampUTC($date)
                        ->setLatitude($loc['geometry']['coordinates'][1])
                        ->setLongitude($loc['geometry']['coordinates'][0])
                        ->setContent($loc)
                        ->setTrip($trip)
                        ->setLocationDatabase($database);
                    $this->entityManager->persist($location);
                    $lastLoc = $loc;
                }
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->entityManager->flush();

        return new InputResponse('ok', $num, $lastLoc);
    }
}
