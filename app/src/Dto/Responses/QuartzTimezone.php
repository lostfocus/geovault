<?php

declare(strict_types=1);

namespace App\Dto\Responses;

use App\Dto\AbstractDto;

readonly class QuartzTimezone extends AbstractDto
{
    public function __construct(
        public string $offset,
        public int $seconds,
        public string $localtime,
        public string $name,
    ) {
    }
}
