<?php

declare(strict_types=1);

namespace App\Dto\Responses;

readonly class FullResponse extends QueryResponse
{
    /**
     * @param \stdClass[] $locations
     */
    public function __construct(
        public array $locations,
    ) {
    }
}
