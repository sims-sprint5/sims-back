<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Handle HTTP exceptions with simple error responses
        if ($exception instanceof HttpException) {
            $message = match ($exception->getStatusCode()) {
                404 => 'Resource not found',
                403 => 'This action is forbidden',
                401 => 'Unauthenticated',
                422 => 'Unprocessable entity',
                500 => 'Server error occurred',
                default => $exception->getMessage() ?: 'An error occurred'
            };

            return response()->json([
                'message' => $message,
            ], $exception->getStatusCode());
        }

        // Handle validation exceptions
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
            ], 422);
        }

        return parent::render($request, $exception);
    }
}
