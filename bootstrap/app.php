<?php

use App\Http\Middleware\EnsureCentralAdmin;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\InitializeTenantFromHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant context must be resolved before the web middleware starts
        // sessions so database-backed sessions can remain tenant-local.
        $middleware->prepend(InitializeTenantFromHost::class);
        $middleware->alias(['central.admin' => EnsureCentralAdmin::class, 'central.domain' => EnsureCentralDomain::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
