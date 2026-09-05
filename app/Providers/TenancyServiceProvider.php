<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    public static string $controllerNamespace = '';

    public function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([Jobs\MigrateDatabase::class])->send(fn (Events\TenantCreated $event) => $event->tenant)->shouldBeQueued(false),
            ],
            Events\TenantDeleted::class => [],
            Events\TenancyInitialized::class => [Listeners\BootstrapTenancy::class],
            Events\TenancyEnded::class => [Listeners\RevertToCentralContext::class],
            Events\SyncedResourceSaved::class => [Listeners\UpdateSyncedResource::class],
        ];
    }

    public function register(): void {}

    public function boot(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) Event::listen($event, $listener instanceof JobPipeline ? $listener->toListener() : $listener);
        }

        $this->app->booted(function (): void {
            if (file_exists(base_path('routes/tenant.php'))) Route::namespace(static::$controllerNamespace)->group(base_path('routes/tenant.php'));
        });

        $kernel = $this->app->make(HttpKernelContract::class);
        $middleware = [Middleware\PreventAccessFromCentralDomains::class, Middleware\InitializeTenancyByDomain::class, Middleware\InitializeTenancyBySubdomain::class, Middleware\InitializeTenancyByDomainOrSubdomain::class, Middleware\InitializeTenancyByPath::class, Middleware\InitializeTenancyByRequestData::class];
        foreach (array_reverse($middleware) as $item) $kernel->prependToMiddlewarePriority($item);
    }
}
