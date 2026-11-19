<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function data(?array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function error(string $message, int $status, array $errors = []): JsonResponse
    {
        $body = ['message' => $message];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
