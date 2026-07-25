<?php

namespace App\Core\Http;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Operation completed successfully',
        array $meta = [],
        int $status = 200
    ): JsonResponse {
        return response()->json(self::payload(true, $message, $data, null, $meta), $status);
    }

    public static function error(
        string $message,
        array $errors = [],
        int $status = 422,
        array $meta = []
    ): JsonResponse {
        return response()->json(self::payload(false, $message, null, $errors, $meta), $status);
    }

    private static function payload(
        bool $success,
        string $message,
        mixed $data,
        ?array $errors,
        array $meta
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => $meta,
        ];
    }
}
