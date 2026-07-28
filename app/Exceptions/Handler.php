<?php

namespace App\Exceptions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (BusinessRuleException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), $e->errors(), $e->status(), $e->meta());
            }

            if ($request->expectsHtml() && $request->isMethod('POST')) {
                return redirect()->back()->withInput()->withErrors(['mapping' => $e->getMessage()]);
            }
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Validation failed', $e->errors(), 422);
            }
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated', [], 401);
            }
        });

        $this->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Forbidden', [], 403);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found', [], 404);
            }
        });

        $this->renderable(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $isConflict = str_starts_with((string) $e->getCode(), '23');

            return ApiResponse::error(
                $isConflict ? 'Database conflict' : 'Unexpected database error',
                [],
                $isConflict ? 409 : 500
            );
        });

        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $message = match ($status) {
                    404 => 'Resource not found',
                    405 => 'Method not allowed',
                    429 => 'Too many requests',
                    default => $status >= 500 ? 'Unexpected server error' : 'Request failed',
                };

                return ApiResponse::error($message, [], $status);
            }
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && app()->environment('production')) {
                return ApiResponse::error('Unexpected server error', [], 500);
            }
        });
    }
}
