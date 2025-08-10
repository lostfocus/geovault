<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Responses\QuartzResponse;
use App\Dto\Responses\QueryResponse;
use App\Entity\Database;
use App\Repository\DatabaseRepository;
use App\Service\LocationService\DatabaseNoFoundException;
use Quartz\Exception;

readonly class LocationService
{
    public function __construct(
        private QuartzService $quartz,
        private DatabaseRepository $databaseRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateInvalidOperationException
     * @throws DatabaseNoFoundException
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function getLast(string $token, ?string $before = null, string $tz = 'UTC', bool $geocode = false): QuartzResponse
    {
        $database = $this->databaseRepository->findOneBy(['readToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNoFoundException('invalid token');
        }

        $dateTime = $this->tryToGuessDate($before, $tz);

        return $this->quartz->getLast($database->getSlug(), $dateTime);
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
     * @throws DatabaseNoFoundException
     * @throws \DateInvalidOperationException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function getFromLocalTime(string $token, string $input): QuartzResponse
    {
        $database = $this->databaseRepository->findOneBy(['readToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNoFoundException('invalid token');
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
     * @throws DatabaseNoFoundException
     */
    public function query(
        string $token, ?string $dateString = null, ?string $startString = null, ?string $endString = null, string $tz = 'UTC', string $format = 'full'): QueryResponse
    {
        $database = $this->databaseRepository->findOneBy(['readToken' => $token]);
        if (!$database instanceof Database || null === $database->getSlug()) {
            throw new DatabaseNoFoundException('invalid token');
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
}
