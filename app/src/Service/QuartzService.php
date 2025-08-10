<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Responses\FullResponse;
use App\Dto\Responses\LinestringResponse;
use App\Dto\Responses\QuartzResponse;
use App\Dto\Responses\QuartzTimezone;
use App\Dto\Responses\QueryResponse;
use App\Service\QuartzService\Candidate;
use App\Service\QuartzService\TimezoneResult;
use p3k\Timezone;
use Quartz\DB;
use Quartz\Exception;
use Quartz\Record;
use Quartz\ResultSet;
use Quartz\Shard;

class QuartzService
{
    // private DB $readDb;
    // private ?DB $writeDb = null;

    /** @var array<string, DB> */
    private array $readDbs = [];

    public function __construct(
        private readonly string $path,
    ) {
        // $this->readDb = new DB(path: $path, mode: 'r');
        // $this->writeDb = new DB(path: $path, mode: 'w');
    }

    /**
     * @throws Exception
     */
    public function query(string $slug, \DateTime $start, \DateTime $end, string $format = 'full'): QueryResponse
    {
        $readDb = $this->getReadDb($slug);
        $result = $readDb->queryRange($start, $end);

        if ('linestring' === $format) {
            return $this->returnLinestring($result, $start);
        }

        return $this->returnFullResults($result);
    }

    private function returnLinestring(ResultSet $result, \DateTime $start): LinestringResponse
    {
        /** @var \stdClass[] $properties */
        $properties = [];
        /** @var \stdClass[] $events */
        $events = [];

        /** @var array<array<int, float>> $coordinates */
        $coordinates = [];

        $timezone = $start->getTimezone();

        foreach ($result as $record) {
            if ($record instanceof Record) {
                $recordData = $this->getRecordData($record);
                $recordDate = $this->getRecordDate($record);

                if (property_exists($recordData->properties, 'action')) {
                    $recordData->properties->unixtime = (int) $recordDate->format('U');
                    $events[] = $recordData;
                } elseif (!property_exists($recordData->properties, 'horizontal_accuracy')
                    || $recordData->properties->horizontal_accuracy <= 5000) {
                    if (property_exists($recordData, 'geometry')) {
                        $coordinates[] = $recordData->geometry->coordinates;
                    } else {
                        $coordinates[] = null;
                    }

                    /** @var \stdClass $props */
                    $props = $recordData->properties;
                    $recordDate->setTimeZone($timezone);
                    $props->timestamp = $recordDate->format('c');
                    $props->unixtime = (int) $recordDate->format('U');
                    $properties[] = $props;
                }
            }
        }

        return new LinestringResponse(
            [
                'type' => 'LineString',
                'coordinates' => $coordinates,
                'properties' => $properties,
            ], $events
        );
    }

    private function returnFullResults(ResultSet $result): FullResponse
    {
        /** @var \stdClass[] $locations */
        $locations = [];
        foreach ($result as $record) {
            if ($record instanceof Record) {
                $recordData = $this->getRecordData($record);

                $locations[] = $recordData;
            }
        }

        return new FullResponse($locations);
    }

    /**
     * @throws \DateInvalidOperationException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function getLast(string $slug, \DateTime $dateTime): QuartzResponse
    {
        $record = $this->findClosestRecord($slug, $dateTime);
        if (null === $record) {
            return new QuartzResponse(null);
        }

        $recordData = $this->getRecordData($record);
        $recordDate = $this->getRecordDate($record);

        $quartzTimezone = null;
        if (
            property_exists($recordData, 'geometry')
            && ($recordData->geometry instanceof \stdClass)
            && property_exists($recordData->geometry, 'coordinates')
            && is_array($recordData->geometry->coordinates)
            && 2 === count($recordData->geometry->coordinates)
            && is_float($recordData->geometry->coordinates[0])
            && is_float($recordData->geometry->coordinates[1])
        ) {
            $local = $this->timezoneForLocation($recordData->geometry->coordinates[1], $recordData->geometry->coordinates[0], $recordDate->format('c'));
            $quartzTimezone = new QuartzTimezone(
                offset: $local->date->format('P'),
                seconds: (int) $local->date->format('Z'),
                localtime: $local->date->format('c'),
                name: $local->name,
            );
        }

        return new QuartzResponse($recordData, $quartzTimezone);
    }

    /**
     * @throws \DateInvalidOperationException
     */
    private function findClosestRecord(string $slug, \DateTime $dateTime): ?Record
    {
        $readDb = $this->getReadDb($slug);
        $shard = $readDb->shardForDate($dateTime);
        if (!$shard instanceof Shard || !$shard->exists()) {
            $date = $dateTime->sub(new \DateInterval('PT86400S'));
            $shard = $readDb->shardForDate($date);
            if (!$shard instanceof Shard || !$shard->exists()) {
                return null;
            }
        }
        $shard->init();
        /** @var Record|null $record */
        $record = null;
        foreach ($shard as $r) {
            if ($r instanceof Record) {
                try {
                    $recordDate = $this->getRecordDate($r);
                    if ($recordDate > $dateTime) {
                        break;
                    }
                    $record = $r;
                } catch (\UnexpectedValueException) {
                    // Skip
                }
            }
        }

        return $record;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \DateInvalidTimeZoneException
     */
    private function timezoneForLocation(float $lat, float $lng, string $date): TimezoneResult
    {
        $tz = Timezone::timezone_for_location($lat, $lng);
        if (!is_string($tz)) {
            $tz = 'UTC';
        }

        return new TimezoneResult($tz, $date);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedStringException
     */
    public function getFromLocalTime(string $slug, string $input): QuartzResponse
    {
        $timezones = $this->getTimezones();

        /** @var Candidate[] $candidates */
        $candidates = [];
        foreach ($timezones as $tz) {
            // Interpret the input date in each timezone
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $input, new \DateTimeZone($tz));
            if (false === $date) {
                throw new \DateMalformedStringException();
            }
            $record = $this->findClosestRecord($slug, $date);
            if ($record instanceof Record) {
                $recordData = $this->getRecordData($record);
                $recordDate = $this->getRecordDate($record);

                if (
                    property_exists($recordData, 'geometry')
                    && ($recordData->geometry instanceof \stdClass)
                    && property_exists($recordData->geometry, 'coordinates')
                    && is_array($recordData->geometry->coordinates)
                    && 2 === count($recordData->geometry->coordinates)
                    && is_float($recordData->geometry->coordinates[0])
                    && is_float($recordData->geometry->coordinates[1])
                ) {
                    $local = $this->timezoneForLocation($recordData->geometry->coordinates[1], $recordData->geometry->coordinates[0], $recordDate->format('c'));
                    $diff = strtotime($local->date->format('c')) - strtotime($date->format('c'));
                    if ($tz === $local->date->format('P')) {
                        $candidates[] = new Candidate(
                            $recordData,
                            $local,
                            $tz,
                            $diff
                        );
                    }
                }
            }
        }

        usort($candidates, static fn (Candidate $a, Candidate $b) => abs($a->diff) < abs($b->diff) ? -1 : 1);
        if (count($candidates) > 0) {
            $responseRecord = $candidates[0];

            return new QuartzResponse(
                $responseRecord->record, new QuartzTimezone(
                    offset: $responseRecord->local->date->format('P'),
                    seconds: (int) $responseRecord->local->date->format('Z'),
                    localtime: $responseRecord->local->date->format('c'),
                    name: $responseRecord->local->name,
                )
            );
        }

        return new QuartzResponse(null);
    }

    /**
     * @return string[]
     *
     * @TODO There has to be a way to make this prettier
     */
    private function getTimezones(): array
    {
        return [
            '-23:00',
            '-22:00',
            '-21:00',
            '-20:00',
            '-19:00',
            '-18:00',
            '-17:00',
            '-16:00',
            '-15:00',
            '-14:00',
            '-13:00',
            '-12:00',
            '-11:00',
            '-10:00',
            '-09:00',
            '-08:00',
            '-07:00',
            '-06:00',
            '-05:00',
            '-04:00',
            '-03:00',
            '-02:00',
            '-01:00',
            '+00:00',
            '+01:00',
            '+02:00',
            '+03:00',
            '+04:00',
            '+05:00',
            '+06:00',
            '+07:00',
            '+08:00',
            '+09:00',
            '+10:00',
            '+11:00',
            '+12:00',
            '+13:00',
            '+14:00',
            '+15:00',
            '+16:00',
            '+17:00',
            '+18:00',
            '+19:00',
            '+20:00',
            '+21:00',
            '+22:00',
            '+23:00',
        ];
    }

    private function getRecordData(Record $record): \stdClass
    {
        /**
         * @noinspection PhpUndefinedFieldInspection
         *
         * @phpstan-ignore property.notFound
         */
        $recordData = $record->data;
        if ($recordData instanceof \stdClass) {
            return $recordData;
        }
        throw new \UnexpectedValueException('Record has no data property');
    }

    private function getRecordDate(Record $record): \DateTime
    {
        /**
         * @noinspection PhpUndefinedFieldInspection
         *
         * @phpstan-ignore property.notFound
         */
        $recordDate = $record->date;
        if ($recordDate instanceof \DateTime) {
            return $recordDate;
        }
        throw new \UnexpectedValueException('Record has no date property');
    }

    private function getReadDb(string $slug): DB
    {
        if (isset($this->readDbs[$slug])) {
            return $this->readDbs[$slug];
        }
        $this->readDbs[$slug] = new DB(path: implode('/', [$this->path, $slug]), mode: 'r');

        return $this->readDbs[$slug];
    }
}
