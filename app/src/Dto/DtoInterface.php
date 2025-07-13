<?php

declare(strict_types=1);

namespace App\Dto;

interface DtoInterface extends \JsonSerializable
{
    /**
     * @return int[]|string[]
     */
    public function keys(): array;

    /**
     * @return array<string|int, string|int|float|bool|array<string|int, mixed>|null>
     */
    public function toArray(): array;
}
