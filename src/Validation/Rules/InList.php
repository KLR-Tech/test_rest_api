<?php

declare(strict_types=1);

namespace App\Validation\Rules;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class InList
{
    /**
     * @param array<string|int> $allowedValues
     */
    public function __construct(public array $allowedValues) {}
}