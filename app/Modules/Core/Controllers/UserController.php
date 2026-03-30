<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => null], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['data' => null]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json(['data' => null]);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['data' => null], 204);
    }
}
