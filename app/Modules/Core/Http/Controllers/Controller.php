<?php

namespace App\Modules\Core\Http\Controllers;

use  App\Modules\Core\Concerns\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponse, AuthorizesRequests;
}
