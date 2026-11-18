<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ForceJsonResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Every API error uses the standard { success, message, errors, data } envelope.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error('Validation failed.', $e->errors(), $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
            }

            // 404 (incl. ModelNotFound), 403 (incl. AuthorizationException), 405, 429, abort(), ...
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error.');

                return ApiResponse::error($message, null, $status, null, $e->getHeaders());
            }

            $debug = (bool) config('app.debug');

            return ApiResponse::error(
                $debug ? $e->getMessage() : 'Server error.',
                $debug ? get_class($e).' in '.$e->getFile().':'.$e->getLine() : null,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        });
    })->create();
