<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets users with the `admin` role through. Use after `auth:sanctum`.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Only admins can perform this action.');
        }

        return $next($request);
    }
}
