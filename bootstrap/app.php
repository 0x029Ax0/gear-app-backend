<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = 500;
            $code = 'INTERNAL_ERROR';
            $message = 'An unexpected error occurred.';
            $errors = null;

            if ($exception instanceof ValidationException) {
                $status = 422;
                $code = 'VALIDATION_FAILED';
                $message = 'The supplied data is invalid.';
                $errors = $exception->errors();
            } elseif ($exception instanceof AuthenticationException) {
                $status = 401;
                $code = 'UNAUTHENTICATED';
                $message = 'Authentication is required.';
            } elseif ($exception instanceof AccessDeniedHttpException) {
                $status = 403;
                $code = 'FORBIDDEN';
                $message = 'You are not authorized to perform this action.';
            } elseif ($exception instanceof NotFoundHttpException) {
                $status = 404;
                $code = 'RESOURCE_NOT_FOUND';
                $message = 'The requested resource was not found.';
            } elseif ($exception instanceof MethodNotAllowedHttpException) {
                $status = 405;
                $code = 'METHOD_NOT_ALLOWED';
                $message = 'The requested method is not allowed.';
            } elseif ($exception instanceof TooManyRequestsHttpException) {
                $status = 429;
                $code = 'RATE_LIMITED';
                $message = 'Too many requests.';
            }

            $response = ['message' => $message, 'code' => $code];

            if ($errors !== null) {
                $response['errors'] = $errors;
            }

            return response()->json($response, $status);
        });
    })->create();
