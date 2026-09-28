<?php

declare(strict_types=1);

namespace App\Exception;

use App\Http\HttpStatusCode;

class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(array $errors)
    {
        parent::__construct('Validation failed', HttpStatusCode::UNPROCESSABLE_ENTITY_422->value);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}