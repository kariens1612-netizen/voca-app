<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', \App\Http\Middleware\ResolveOrganisation::class);
        $middleware->prependToGroup('api', \App\Http\Middleware\ResolveOrganisation::class);
        $middleware->appendToGroup('web', \Illuminate\Session\Middleware\AuthenticateSession::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureTenantAccess::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureTenantAccess::class);
        $middleware->alias([
            'ministry.role' => \App\Http\Middleware\EnsureMinistryRole::class,
            'organisation.admin' => \App\Http\Middleware\EnsureOrganisationAdmin::class,
            'finance.access' => \App\Http\Middleware\EnsureFinanceAccess::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();

$app->usePublicPath(dirname(__DIR__, 2));

return $app;
