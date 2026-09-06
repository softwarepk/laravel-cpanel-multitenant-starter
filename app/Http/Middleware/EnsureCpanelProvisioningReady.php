<?php

namespace App\Http\Middleware;

use App\Services\CpanelProvisioningReadiness;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCpanelProvisioningReady
{
    public function __construct(private readonly CpanelProvisioningReadiness $readiness) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->readiness->isReady()) {
            return $next($request);
        }

        $message = 'Production tenant provisioning is not configured for this environment.';

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $message,
                'errors' => ['provisioning' => [$message]],
                'missing' => $this->readiness->missingRequirements(),
            ], 422);
        }

        return new RedirectResponse(route('central.tenants.create'));
    }
}
