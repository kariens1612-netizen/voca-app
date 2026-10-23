<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $accountAccess = $request->is('auth/login', 'auth/logout', 'auth/forgot-password', 'auth/reset-password',
            'login', 'platform/login', 'forgot-password', 'reset-password/*', 'api/platform/impersonation/stop');
        if ($user && ! $accountAccess && ! $user->canAccessOrganisation($request->attributes->get('organisation')?->id)) {
            // Suspension overrides any legacy ministry role.
            abort(403, 'Your account does not have active access to this organisation.');
        }
        $response = $next($request);
        if ($user || $request->is('auth/*', 'login', 'forgot-password', 'reset-password/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
