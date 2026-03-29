<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
}
