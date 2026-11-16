<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\RequireHttps::class);
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => '/account');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'smtp_password', 'data.password', 'data.smtp_password']);
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            return request()->routeIs('password.*')
                ? \App\Http\Middleware\SensitiveAuthResponse::protect($response)
                : $response;
        });
    })->create();
