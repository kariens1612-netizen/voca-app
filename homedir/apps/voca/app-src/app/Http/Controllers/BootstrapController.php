<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function show(Request $request)
    {
        $organisation = $request->attributes->get('organisation');
        $user = $request->user();
        $organisationRole = $user?->organisationMemberships()->where('organisation_id', $organisation->id)
            ->where('status', 'active')->value('role');
        $memberships = $user?->memberships()->with(['ministry', 'roleRecord'])->get()
            ->filter(fn ($membership) => $membership->ministry)
            ->map(fn ($membership) => [
                'role' => $membership->roleSlug(), 'entity' => $membership->ministry->entity,
                'slug' => $membership->ministry->slug, 'name' => $membership->ministry->name,
            ])->values() ?? collect();
        $preferences = $user ? UserPreference::firstOrCreate(
            ['organisation_id' => $organisation->id, 'user_id' => $user->id],
            ['locale' => data_get($organisation->settings, 'locale', 'en'), 'timezone' => data_get($organisation->settings, 'timezone', 'Africa/Johannesburg')]
        ) : null;

        return response()->json([
            'organisation' => $organisation->publicPayload(),
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_superadmin' => $user->is_superadmin] : null,
            'organisation_role' => $organisationRole,
            'memberships' => $memberships,
            'permissions' => [
                'platform_admin' => (bool) $user?->is_superadmin,
                'manage_organisation' => (bool) ($user?->is_superadmin || in_array($organisationRole, ['owner', 'admin'], true)),
                'manage_owners' => (bool) ($user?->is_superadmin || $organisationRole === 'owner'),
                'finance_access' => $this->canAccessFinance($request),
            ],
            'preferences' => $preferences,
        ]);
    }

    private function canAccessFinance(Request $request): bool
    {
        $user = $request->user();
        $organisation = $request->attributes->get('organisation');

        if (! $user) {
            return false;
        }

        if ($user->is_superadmin || $user->organisationMemberships()
            ->where('organisation_id', $organisation?->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists()) {
            return true;
        }

        return $user->memberships()
            ->whereHas('ministry', function ($query) use ($organisation) {
                $query->where('organisation_id', $organisation?->id)
                    ->whereIn('slug', config('finance.allowed_ministry_slugs', []));
            })
            ->exists();
    }

    public function preferences(Request $request)
    {
        $data = $request->validate([
            'locale' => ['sometimes', 'string', 'max:12'], 'timezone' => ['sometimes', 'timezone'],
            'notifications' => ['sometimes', 'array'], 'dashboard' => ['sometimes', 'array'],
            'ministry_subscriptions' => ['sometimes', 'array'], 'ministry_subscriptions.*' => ['integer'],
        ]);
        $organisation = $request->attributes->get('organisation');
        $preferences = UserPreference::updateOrCreate(
            ['organisation_id' => $organisation->id, 'user_id' => $request->user()->id], $data
        );
        return response()->json(['preferences' => $preferences]);
    }
}
