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

        $credentials['website_member_id'] = null;
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (! $request->user()->canAccessOrganisation($request->attributes->get('organisation')?->id)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return response()->json(['message' => 'Your account does not have active access to this organisation.'], 403);
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
            ->filter(fn ($membership) => $membership->ministry)
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
                'is_voca_member' => (bool) $user->website_member_id,
            ],
            'organisation_role' => $user->organisationMemberships()->where('organisation_id', $request?->attributes->get('organisation')?->id)->where('status', 'active')->value('role'),
            'memberships' => $memberships,
            'impersonation' => $request?->session()->has('impersonator_id') ? [
                'active' => true,
                'organisation_id' => $request->session()->get('impersonated_organisation_id'),
            ] : null,
        ];
    }
}
