<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Standardized API response envelope.
 *
 * All responses follow the format:
 * {
 *     "status": "success"|"error",
 *     "message": "...",
 *     "data": { ... } | [ ... ],
 *     "meta": { ... }  // pagination, timestamps, etc.
 * }
 */
trait ApiResponse
{
    /**
     * Return a success response with data.
     */
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a success response wrapping a JsonResource.
     */
    protected function resource(
        JsonResource $resource,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        $data = $resource->resolve();

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a paginated collection with meta information.
     */
    protected function paginated(
        ResourceCollection $collection,
        string $message = 'OK',
    ): JsonResponse {
        $response = $collection->response()->getData(true);

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $response['data'],
            'meta' => $response['meta'] ?? [],
            'links' => $response['links'] ?? [],
        ]);
    }

    /**
     * Return a success response with no data (e.g. after delete).
     */
    protected function deleted(string $message = 'Resource deleted successfully.'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => null,
        ]);
    }

    /**
     * Return an error response.
     */
    protected function error(
        string $message = 'An error occurred.',
        int $status = 400,
        mixed $errors = null,
    ): JsonResponse {
        $payload = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
