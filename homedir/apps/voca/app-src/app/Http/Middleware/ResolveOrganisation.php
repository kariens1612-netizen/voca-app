<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Models\OrganisationDomain;
use App\Tenancy\CurrentOrganisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Schema;

class ResolveOrganisation
{
    public function __construct(private CurrentOrganisation $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('organisations') || ! Schema::hasTable('organisation_domains')) {
            return $next($request);
        }
        $host = strtolower(explode(':', $request->getHost())[0]);
        $organisation = null;
        $impersonatorId = $request->hasSession() ? $request->session()->get('impersonator_id') : null;
        $impersonatedOrganisationId = $request->hasSession() ? $request->session()->get('impersonated_organisation_id') : null;
        if ($impersonatorId && $impersonatedOrganisationId
            && \App\Models\User::query()->whereKey($impersonatorId)->where('is_superadmin', true)->exists()) {
            $organisation = Organisation::query()->find($impersonatedOrganisationId);
        }
        $organisation ??= OrganisationDomain::query()->where('host', $host)->whereNotNull('verified_at')->first()?->organisation;
        $base = strtolower(config('tenancy.base_domain'));

        if (! $organisation && str_ends_with($host, '.'.$base)) {
            $slug = substr($host, 0, -strlen('.'.$base));
            if ($slug && ! str_contains($slug, '.')) $organisation = Organisation::query()->where('slug', $slug)->first();
        }

        if (! $organisation && (in_array($host, config('tenancy.trusted_hosts', []), true) || $host === $base)) {
            $organisation = Organisation::query()->where('slug', config('tenancy.default_slug'))->first();
        }

        if (! $organisation) return response()->json(['message' => 'Organisation not found.'], 404);
        if ($organisation->status !== 'active') return response()->json(['message' => 'Organisation is unavailable.'], 423);

        $this->current->set($organisation);
        $request->attributes->set('organisation', $organisation);
        app()->instance(Organisation::class, $organisation);

        try { return $next($request); }
        finally { $this->current->set(null); }
    }
}
