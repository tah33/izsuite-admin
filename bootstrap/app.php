<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckEmailVerified;
use App\Http\Middleware\ConvertAdminRedirectsToJson;
use App\Http\Middleware\EnsureAppActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\LogApiWriteActivity;
use App\Http\Middleware\PermissionCheckMiddleware;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Middleware\SetSessionLocale;
use App\Http\Middleware\SetUserTimezone;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app runs behind host nginx, which itself sits behind Cloudflare,
        // so every request arrives from 127.0.0.1 over plain HTTP. Without
        // this Laravel builds URLs from what it sees rather than from
        // X-Forwarded-Proto: `https://api.izsuite.com/` redirects to
        // `http://api.izsuite.com/login`, and password-reset links go out as
        // http://. Cloudflare's Always Use HTTPS masks it by bouncing the
        // request back, which just turns one redirect into three.
        //
        // `*` is safe here because nothing reaches the app except through our
        // own nginx on loopback.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin'      => AdminMiddleware::class,
            'permission' => PermissionCheckMiddleware::class,
            'role'       => EnsureRole::class,
            'verified'   => CheckEmailVerified::class,
            'workspace'  => ResolveWorkspace::class,
            'app'        => EnsureAppActive::class,
        ]);

        $middleware->web(append: [
            SetUserTimezone::class,
            SetSessionLocale::class,
            ConvertAdminRedirectsToJson::class,
        ]);

        $middleware->api(append: [
            SetUserTimezone::class,
            LogApiWriteActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'The given data was invalid.',
                    'errors'  => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $status  = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $e->getMessage();

            if (! is_string($message) || trim($message) === '') {
                $message = $status >= 500 ? 'Server error.' : 'Request failed.';
            }

            $payload = [
                'message' => $message,
            ];

            if (config('app.debug')) {
                $payload['exception'] = class_basename($e);
                $payload['file']      = $e->getFile();
                $payload['line']      = $e->getLine();
            }

            return response()->json($payload, $status);
        });
    })->create();
