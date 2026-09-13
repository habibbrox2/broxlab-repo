<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\StartLegacySession;
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
        // Start the shared legacy native PHP session (cookie BROXBHAI_SESSION,
        // save path storage/tmp/sessions) BEFORE Laravel's own StartSession so
        // the LegacySessionGuard can read/write the state both apps share.
        $middleware->prependToGroup('web', StartLegacySession::class);

        // The remember-me cookie must stay unencrypted: the legacy PHP app
        // reads it with json_decode($_COOKIE[...]) and cannot decrypt
        // Laravel's encrypted cookies.
        $middleware->encryptCookies(except: ['broxbhai_remember']);

        // Admin gate (legacy admin_only / admin_or_super_only parity)
        $middleware->alias(['admin' => EnsureAdmin::class]);

        // Medicines scraper API uses dual-auth (MEDEX_REFRESH_TOKEN OR CSRF) handled
        // inside MedicinesController::requireApiAuth — the token path must not be
        // blocked by Laravel's CSRF middleware first.
        $middleware->validateCsrfTokens(except: [
            'api/medicines/refresh',
            'api/medicines/proxy',
            'api/medicines/fetch-page',
            'api/medicines/save-data',
            'api/translate',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
