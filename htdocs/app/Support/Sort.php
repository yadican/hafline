<?php

namespace App\Support;

final readonly class Sort
{
    public const DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        public string $field,
        public string $direction = 'asc',
    ) {}
}
