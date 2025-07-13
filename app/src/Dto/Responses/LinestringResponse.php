<?php

declare(strict_types=1);

namespace App\Dto\Responses;

readonly class LinestringResponse extends QueryResponse
{
    /**
     * @param array<string, mixed> $linestring
     * @param \stdClass[]          $events
     */
    public function __construct(
        public array $linestring,
        public array $events,
    ) {
    }
}
