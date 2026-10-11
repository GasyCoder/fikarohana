<?php

use App\Http\Middleware\RequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestId::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->report(function (Throwable $exception) {
            if (request()->is('api/*') || request()->is('sanctum/*')) {
                Log::error('API request failed.', [
                    'exception_type' => $exception::class,
                    'request_id' => request()->attributes->get('request_id'),
                ]);

                return false; // Do not log exception messages, SQL or traces with private data.
            }
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->is('sanctum/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            [$code, $message] = match ($status) {
                400 => ['bad_request', 'A first-party SPA session is required.'],
                401 => ['unauthenticated', 'Authentication failed.'],
                403 => ['forbidden', 'Access denied.'],
                404 => ['not_found', 'Resource not found.'],
                405 => ['method_not_allowed', 'Method not allowed.'],
                419 => ['csrf_mismatch', 'CSRF token mismatch.'],
                422 => ['validation_error', 'The given data was invalid.'],
                429 => ['rate_limited', 'Too many requests.'],
                default => ['server_error', 'An unexpected error occurred.'],
            };
            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            return response()->json(['error' => [
                'code' => $code,
                'message' => $message,
                'fields' => $exception instanceof ValidationException ? $exception->errors() : (object) [],
                'request_id' => $request->attributes->get('request_id'),
            ]], $status, $headers);
        });
    })->create();
