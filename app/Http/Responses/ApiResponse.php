<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the standard JSON envelope used by every API response:
 *
 * {
 *   "success": bool,
 *   "message": string,
 *   "errors":  array|string|null,
 *   "data":    array|object
 * }
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success.',
        int $status = Response::HTTP_OK,
        array $headers = [],
    ): JsonResponse {
        return static::make(true, $message, null, $data, $status, $headers);
    }

    public static function error(
        string $message,
        array|string|null $errors = null,
        int $status = Response::HTTP_BAD_REQUEST,
        mixed $data = null,
        array $headers = [],
    ): JsonResponse {
        return static::make(false, $message, $errors, $data, $status, $headers);
    }

    protected static function make(
        bool $success,
        string $message,
        array|string|null $errors,
        mixed $data,
        int $status,
        array $headers,
    ): JsonResponse {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data ?? new \stdClass, // empty data is always serialized as {}
        ], $status, $headers);
    }
}
