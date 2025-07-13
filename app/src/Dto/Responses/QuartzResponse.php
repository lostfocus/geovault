<?php

declare(strict_types=1);

namespace App\Dto\Responses;

use App\Dto\AbstractDto;

readonly class QuartzResponse extends AbstractDto
{
    public function __construct(
        public ?\stdClass $data = null,
        public ?QuartzTimezone $timezone = null,
    ) {
    }
}
