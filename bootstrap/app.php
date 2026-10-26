<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        $middleware->throttleWithRedis();
        $middleware->trimStrings(except: ['pnpki_password']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['pnpki_password']);
        $isApiRequest = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(
            $isApiRequest,
        );

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json(['message' => 'Resource not found.'], JsonResponse::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request) || $exception instanceof HttpExceptionInterface) {
                return null;
            }

            return response()->json(['message' => 'Server error.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
