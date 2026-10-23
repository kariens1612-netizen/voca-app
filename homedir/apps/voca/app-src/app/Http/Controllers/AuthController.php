<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $request->session()->regenerate();

        return response()->json($this->payload($request->user(), $request));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json($this->payload($request->user(), $request));
    }

    protected function payload($user, ?Request $request = null): array
    {
        if (! $user) {
            return [
                'user' => null,
                'memberships' => [],
            ];
        }

        $memberships = $user->memberships()
            ->with(['ministry', 'roleRecord'])
            ->get()
            ->map(function ($membership) {
                return [
                    'role' => $membership->roleSlug(),
                    'entity' => $membership->ministry->entity,
                    'slug' => $membership->ministry->slug,
                    'name' => $membership->ministry->name,
                ];
            })
            ->values();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_superadmin' => $user->is_superadmin,
            ],
            'memberships' => $memberships,
            'impersonation' => $request?->session()->has('impersonator_id') ? [
                'active' => true,
                'organisation_id' => $request->session()->get('impersonated_organisation_id'),
            ] : null,
        ];
    }
}
