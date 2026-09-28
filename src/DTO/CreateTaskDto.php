<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validation\Rules\InList;
use App\Validation\Rules\MinLength;
use App\Validation\Rules\MaxLength;
use App\Validation\Rules\Required;

class CreateTaskDto extends AbstractDto
{
    #[Required]
    #[MinLength(5)]
    #[MaxLength(250)]
    public private(set) string $title {
        set => htmlspecialchars((string)trim($value), ENT_QUOTES, 'UTF-8');
    }

    #[InList([0,1])]
    public private(set) ?int $completed = 0;

    #[MaxLength(65000)]
    public private(set) ?string $dscr = null {
        set => $value !== null ? trim($value) : null;
    }
/*        
    public private(set) ?string $dueDate = null;
*/    
}