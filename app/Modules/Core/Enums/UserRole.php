<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case LAB_MANAGER = 'lab_manager';
    case STUDENT = 'student';
}
