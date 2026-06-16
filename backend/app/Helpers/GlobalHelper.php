<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class GlobalHelper
{
    public static function apiSuccess($data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data ?? (object) [],
        ], $status);
    }

    public static function apiError(string $code, string $message, int $status = 400, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => $data,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
