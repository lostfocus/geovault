<?php

namespace App\Dto\Responses;

use App\Dto\AbstractDto;

readonly class InputResponse extends AbstractDto
{
    /**
     * @param array<string, mixed>|null $lastLoc
     */
    public function __construct(
        public int $num,
        public ?array $lastLoc,
    ) {
    }
}
