<?php

declare(strict_types=1);

namespace App\Dto;

abstract readonly class AbstractDto implements DtoInterface
{
    public function keys(): array
    {
        return array_keys(get_object_vars($this));
    }

    /**
     * @return array<string|int, mixed>
     *
     * @throws \JsonException
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @throws \JsonException
     */
    public function toArray(): array
    {
        $return = [];

        foreach (get_object_vars($this) as $key => $value) {
            $mappedValue = $this->mapValue($value);
            $return[$key] = $mappedValue;
        }

        return $return;
    }

    /**
     * @return string|int|float|bool|array<string|int, mixed>|null
     *
     * @throws \JsonException
     */
    private function mapValue(mixed $value): string|int|float|bool|array|null
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map($this->mapValue(...), $value);
        }

        if ($value instanceof \stdClass) {
            $return = json_decode(json: json_encode(value: $value, flags: JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($return));

            return $return;
        }

        return null;
    }
}
