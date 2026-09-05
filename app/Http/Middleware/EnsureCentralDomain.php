<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array(strtolower($request->getHost()), config('tenancy.central_domains', []), true), 404);

        return $next($request);
    }
}
