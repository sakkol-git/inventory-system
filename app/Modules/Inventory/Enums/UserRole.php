<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case LAB_MANAGER = 'lab_manager';
    case STUDENT = 'student';
}
