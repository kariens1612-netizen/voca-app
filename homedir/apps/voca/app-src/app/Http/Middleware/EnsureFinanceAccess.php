<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);
        abort_unless($this->allowed($request), 403);

        return $next($request);
    }

    private function allowed(Request $request): bool
    {
        $user = $request->user();
        $organisation = $request->attributes->get('organisation');

        if ($user?->is_superadmin) {
            return true;
        }

        if ($user?->organisationMemberships()
            ->where('organisation_id', $organisation?->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists()) {
            return true;
        }

        return $user?->memberships()
            ->whereHas('ministry', function ($query) use ($organisation) {
                $query->where('organisation_id', $organisation?->id)
                    ->whereIn('slug', config('finance.allowed_ministry_slugs', []));
            })
            ->exists() ?? false;
    }
}
