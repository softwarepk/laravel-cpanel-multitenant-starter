<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContinueAfterClientDisconnect
{
    public function handle(Request $request, Closure $next): Response
    {
        ignore_user_abort(true);

        return $next($request);
    }
}
