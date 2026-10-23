<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $organisation = $request->attributes->get('organisation');
        return $organisation?->moduleEnabled($module)
            ? $next($request)
            : response()->json(['message' => 'This feature is not enabled.'], 404);
    }
}
