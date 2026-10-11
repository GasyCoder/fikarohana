<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSpaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->hasSession(), 400, 'A first-party SPA session is required.');

        return $next($request);
    }
}
