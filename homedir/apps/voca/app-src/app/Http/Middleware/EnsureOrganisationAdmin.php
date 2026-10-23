<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationAdmin
{
    public function handle(Request $request, Closure $next, string $minimum = 'admin'): Response
    {
        $user = $request->user();
        $organisation = $request->attributes->get('organisation');
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->is_superadmin) return $next($request);
        $roles = $minimum === 'owner' ? ['owner'] : ['owner', 'admin'];
        $allowed = $user->organisationMemberships()->where('organisation_id', $organisation?->id)
            ->where('status', 'active')->whereIn('role', $roles)->exists();
        return $allowed ? $next($request) : response()->json(['message' => 'Forbidden.'], 403);
    }
}
