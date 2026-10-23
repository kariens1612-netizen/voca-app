<?php

namespace App\Http\Middleware;

use App\Models\Ministry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMinistryRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role = 'user'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->is_superadmin) {
            return $next($request);
        }

        $ministry = $request->route('ministry');

        if (! $ministry instanceof Ministry) {
            return response()->json(['message' => 'Ministry not resolved.'], 404);
        }

        $organisation = $request->attributes->get('organisation');
        if (! $organisation || (int) $ministry->organisation_id !== (int) $organisation->id) {
            return response()->json(['message' => 'Ministry not found.'], 404);
        }

        if ($user->organisationMemberships()->where('organisation_id', $organisation->id)
            ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists()) {
            return $next($request);
        }

        $membership = $user->memberships()
            ->where('ministry_id', $ministry->id)
            ->with('roleRecord')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role === 'admin' && $membership->roleSlug() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role === 'leader' && ! in_array($membership->roleSlug(), ['admin', 'leader'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role === 'member' && ! in_array($membership->roleSlug(), ['admin', 'leader', 'member'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
