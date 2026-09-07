<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantFromHost
{
    public function __construct(private readonly InstallationState $installation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $centralDomains = config('tenancy.central_domains', []);
        $installerPath = $request->is('install') || $request->is('install/*');

        if ($this->installation->requiresInstallation()) {
            if ($request->is('up')) {
                return $next($request);
            }

            if (! $request->isSecure()) {
                return redirect()->away('https://'.$host.($installerPath ? $request->getRequestUri() : '/install'), 308);
            }

            return $installerPath ? $next($request) : redirect('/install');
        }

        if ($installerPath) {
            if (in_array($host, $centralDomains, true)) {
                return redirect('/central/login');
            }

            abort(404);
        }

        if (in_array($host, $centralDomains, true)) {
            if ($request->is('up') || $request->is('central') || $request->is('central/*')) {
                if ($this->shouldRedirectToHttps($request)) {
                    return redirect()->away($this->httpsUrl($request), 308);
                }

                return $next($request);
            }
            abort(404);
        }

        $tenant = Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $host)->where('status', 'active'))->first();
        abort_unless($tenant && $tenant->isActive(), 404);

        if ($this->shouldRedirectToHttps($request)) {
            return redirect()->away($this->httpsUrl($request), 308);
        }

        tenancy()->initialize($tenant);
        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }

    private function shouldRedirectToHttps(Request $request): bool
    {
        return (bool) config('central.force_https', false) && ! $request->isSecure();
    }

    private function httpsUrl(Request $request): string
    {
        return 'https://'.$request->getHost().$request->getRequestUri();
    }
}
