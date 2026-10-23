<?php

namespace App\Http\Controllers;

use App\Models\Ministry;
use Illuminate\Http\Request;

class SuperadminRosterController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->is_superadmin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $ministries = Ministry::query()
            ->with(['memberships.user:id,name,email', 'memberships.roleRecord'])
            ->orderBy('name')
            ->get()
            ->map(function ($ministry) {
                $members = $ministry->memberships
                    ->map(function ($membership) {
                        return [
                            'id' => $membership->user->id,
                            'name' => $membership->user->name,
                            'email' => $membership->user->email,
                            'role' => $membership->roleSlug(),
                        ];
                    })
                    ->values();

                return [
                    'id' => $ministry->id,
                    'entity' => $ministry->entity,
                    'slug' => $ministry->slug,
                    'name' => $ministry->name,
                    'description' => $ministry->description,
                    'members' => $members,
                ];
            })
            ->values();

        return response()->json(['ministries' => $ministries]);
    }
}
