<?php

declare(strict_types=1);

namespace App\Service\QuartzService;

class Candidate
{
    public function __construct(
        public \stdClass $record,
        public TimezoneResult $local,
        public string $tz,
        public int $diff,
    ) {
    }
}
