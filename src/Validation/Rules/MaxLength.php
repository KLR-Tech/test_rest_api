<?php

declare(strict_types=1);

namespace App\Validation\Rules;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MaxLength
{
    public function __construct(public int $length) {}
}