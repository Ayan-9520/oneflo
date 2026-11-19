<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => self::wantsJson($request),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! self::wantsJson($request)) {
                return null;
            }

            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors[$field] = array_values(array_map(
                    static fn (mixed $message): string => (string) $message,
                    $messages,
                ));
            }

            return ApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'The given data was invalid.',
                $exception->status,
                $errors,
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! self::wantsJson($request)) {
                return null;
            }

            return ApiResponse::error('Authentication is required.', 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! self::wantsJson($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = match ($status) {
                401 => 'Authentication is required.',
                403 => 'You are not allowed to perform this action.',
                404 => 'The requested resource was not found.',
                419 => 'The page expired. Refresh and try again.',
                default => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'The request could not be completed.',
            };

            return ApiResponse::error($message, $status);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! self::wantsJson($request) || $exception instanceof HttpExceptionInterface || $exception instanceof ValidationException || $exception instanceof AuthenticationException) {
                return null;
            }

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'The request could not be completed.';

            return ApiResponse::error($message !== '' ? $message : 'The request could not be completed.', 500);
        });
    }

    private static function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
