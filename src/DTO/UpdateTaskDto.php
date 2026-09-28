<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validation\Rules\InList;
use App\Validation\Rules\MinLength;
use App\Validation\Rules\MaxLength;

class UpdateTaskDto extends AbstractDto
{
    #[MinLength(3)]
    #[MaxLength(250)]
    public private(set) ?string $title = null {
        set => ($value !== null && trim($value) !== '') 
                ? htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') 
                : null;
    }

    #[MaxLength(65000)]
    public private(set) ?string $dscr = null {
        set => $value !== null ? trim($value) : null;
    }

    #[InList([0,1])]
    public private(set) ?int $completed;

    #[InList(['todo', 'in_progress', 'completed', 'archived'])]
    public private(set) ?string $status = null {
        set => $value !== null ? strtolower(trim($value)) : null;
    }

    public private(set) ?string $dueDate = null;

    /**
     * Helper method to return only the fields that were actually provided in the request payload.
     */
    public function getPresentFields(): array
    {
        $fields = [];
        foreach (get_object_vars($this) as $property => $value) {
            if ($value !== null) {
                $fields[$property] = $value;
            }
        }
        return $fields;
    }
}