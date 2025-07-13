<?php

declare(strict_types=1);

namespace App\Service\QuartzService;

class TimezoneResult
{
    public \DateTime $date;

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function __construct(
        public string $name,
        ?string $date = null,
    ) {
        if (null === $date) {
            $this->date = new \DateTime()->setTimezone(new \DateTimeZone($this->name));
        } else {
            $this->date = new \DateTime($date)->setTimezone(new \DateTimeZone($this->name));
        }
    }
}
