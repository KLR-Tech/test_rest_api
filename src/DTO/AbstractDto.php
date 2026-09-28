<?php

declare(strict_types=1);

namespace App\DTO;

use App\Exception\ValidationException;
use App\Validation\Rules\Email;
use App\Validation\Rules\MinLength;
use App\Validation\Rules\MaxLength;
use App\Validation\Rules\Required;
use App\Validation\Rules\InList;
use ReflectionClass;
use ReflectionProperty;

abstract class AbstractDto
{
    /**
     * Hydrates and validates the DTO instance.
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        
        // PHP 8.4: Create instance without calling constructor
        $instance = $reflection->newInstanceWithoutConstructor();
        $errors = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $value = $data[$name] ?? null;

            // Validate property rules
            self::validateProperty($property, $value, $errors);

            if ($value !== null && !isset($errors[$name])) {
                // Set value on instance (triggers PHP 8.4 property hooks if present)
                $property->setValue($instance, $value);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $instance;
    }

    private static function validateProperty(ReflectionProperty $property, mixed $value, array &$errors): void
    {
        $propertyName = $property->getName();

        foreach ($property->getAttributes() as $attribute) {
            $rule = $attribute->newInstance();

            if ($rule instanceof Required && ($value === null || $value === '')) {
                $errors[$propertyName][] = "The field {$propertyName} is required.";
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($rule instanceof Email) {
                if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$propertyName][] = "The field {$propertyName} must be a valid email address.";
                }
            }

            if ($rule instanceof MinLength) {
                if(is_string($value) && mb_strlen($value) < $rule->length) {
                    $errors[$propertyName][] = "The field {$propertyName} must be at least {$rule->length} characters.";
                }
            }

            if ($rule instanceof MaxLength) {
                if(is_string($value) && mb_strlen($value) > $rule->length) {
                    $errors[$propertyName][] = "The field {$propertyName} must not exceed {$rule->length} characters.";
                }
            }


            if ($rule instanceof InList && !in_array($value, $rule->allowedValues, true)) {
                $errors[$propertyName][] = "The field {$propertyName} must be one of: {implode(', ', $rule->allowedValues)}.";
            }
        }
    }
}