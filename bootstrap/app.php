<?php

use App\Exceptions\Handler\ApiExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware aliases
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'auth.cookie' => \App\Http\Middleware\AuthenticateWithCookie::class,
            'company.scope' => \App\Http\Middleware\ResolveCompany::class,
        ]);

        // Add cookie authentication middleware to API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\AuthenticateWithCookie::class,
        ]);

        $middleware->redirectGuestsTo(fn() => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Handle all other API exceptions
        $exceptions->render(function (\Throwable $e) {
            return match (true) {
                $e instanceof \App\Exceptions\AuthenticationException => ApiExceptionHandler::unauthenticated(),
                $e instanceof \Illuminate\Auth\AuthenticationException => ApiExceptionHandler::unauthenticated(),
                $e instanceof \Illuminate\Validation\ValidationException => ApiExceptionHandler::validationError($e->errors()),
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => ApiExceptionHandler::notFound(),
                $e instanceof \Illuminate\Auth\Access\AuthorizationException => ApiExceptionHandler::forbidden(),
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException => ApiExceptionHandler::httpError(
                    $e->getMessage(),
                    $e->getStatusCode()
                ),
                default => ApiExceptionHandler::genericError($e),
            };
        });
    })->create();
