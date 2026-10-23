<?php

namespace App\Http\Controllers;

use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MinistryMemberController extends Controller
{
    public function index(Request $request, Ministry $ministry)
    {
        $members = $ministry->memberships()
            ->with(['user:id,name,email', 'roleRecord'])
            ->get()
            ->map(fn ($membership) => $this->payload($membership))
            ->values();

        return response()->json(['members' => $members]);
    }

    public function available(Request $request, Ministry $ministry)
    {
        $search = trim((string) $request->input('search', ''));
        $limit = (int) $request->input('limit', 20);
        $limit = max(5, min(50, $limit));

        $query = User::query()
            ->select(['id', 'name', 'email'])
            ->where(function ($query) use ($ministry) {
                $query->whereHas('organisationMemberships', fn ($q) => $q->where('organisation_id', $ministry->organisation_id)->where('status', 'active'))
                    ->orWhereHas('memberships', fn ($q) => $q->where('organisation_id', $ministry->organisation_id));
            })
            ->whereDoesntHave('memberships', function ($builder) use ($ministry) {
                $builder->where('ministry_id', $ministry->id);
            });

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json(['users' => $users]);
    }

    public function store(Request $request, Ministry $ministry)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:50', Rule::exists('roles', 'slug')],
        ]);

        $roleSlug = $data['role'] ?? 'member';
        $roleRecord = Role::query()->where('slug', $roleSlug)->first();

        abort_unless(User::findOrFail($data['user_id'])->canAccessOrganisation($ministry->organisation_id), 404);

        $membership = MinistryMembership::updateOrCreate(
            [
                'ministry_id' => $ministry->id,
                'user_id' => $data['user_id'],
            ],
            [
                'role' => $roleSlug,
                'role_id' => $roleRecord?->id,
            ]
        );

        $membership->loadMissing(['user:id,name,email', 'roleRecord']);

        return response()->json($this->payload($membership));
    }

    public function register(Request $request, Ministry $ministry)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:50', Rule::exists('roles', 'slug')],
        ]);

        $email = strtolower(trim($data['email']));
        $roleSlug = $data['role'] ?? 'member';
        $roleRecord = Role::query()->where('slug', $roleSlug)->first();

        [$membership, $createdUser] = DB::transaction(function () use ($data, $email, $ministry, $roleSlug, $roleRecord) {
            $user = User::query()->where('email', $email)->first();
            $createdUser = false;

            if (! $user) {
                $user = User::create([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'is_superadmin' => false,
                ]);
                $createdUser = true;
            }

            $membership = MinistryMembership::updateOrCreate(
                [
                    'ministry_id' => $ministry->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $roleSlug,
                    'role_id' => $roleRecord?->id,
                ]
            );

            return [$membership, $createdUser];
        });

        $membership->loadMissing(['user:id,name,email', 'roleRecord']);
        app(\App\Services\AccountInvitation::class)->send($membership->user, $request->attributes->get('organisation'));

        return response()->json([
            ...$this->payload($membership),
            'created_user' => $createdUser,
        ], $createdUser ? 201 : 200);
    }

    public function update(Request $request, Ministry $ministry, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'max:50', Rule::exists('roles', 'slug')],
        ]);

        $membership = MinistryMembership::query()
            ->where('ministry_id', $ministry->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        $roleRecord = Role::query()->where('slug', $data['role'])->first();
        $membership->role = $data['role'];
        $membership->role_id = $roleRecord?->id;
        $membership->save();
        $membership->loadMissing(['user:id,name,email', 'roleRecord']);

        return response()->json($this->payload($membership));
    }

    public function destroy(Request $request, Ministry $ministry, User $user)
    {
        $removed = MinistryMembership::query()
            ->where('ministry_id', $ministry->id)
            ->where('user_id', $user->id)
            ->delete();

        if (! $removed) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        return response()->noContent();
    }

    public function export(Request $request, Ministry $ministry)
    {
        $members = $ministry->memberships()
            ->with(['user:id,name,email', 'roleRecord'])
            ->get()
            ->map(fn ($membership) => $this->payload($membership))
            ->values();

        $filename = 'roster-' . $ministry->slug . '-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($members) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'email', 'role']);
            foreach ($members as $member) {
                fputcsv($handle, [$member['name'], $member['email'], $member['role']]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request, Ministry $ministry)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        if (! $handle) {
            return response()->json(['message' => 'Failed to read file.'], 422);
        }

        $createdUsers = 0;
        $addedMemberships = 0;
        $updatedMemberships = 0;
        $skippedRows = 0;
        $errors = [];

        $rowIndex = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            $row = array_map('trim', $row);
            if (count($row) < 2) {
                $skippedRows++;
                continue;
            }

            if ($rowIndex === 1 && $this->looksLikeHeader($row)) {
                continue;
            }

            [$name, $email, $roleValue] = array_pad($row, 3, null);
            $email = strtolower((string) $email);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowIndex}: invalid email.";
                continue;
            }

            $roleSlug = $this->normalizeRole($roleValue);
            $roleRecord = Role::query()->where('slug', $roleSlug)->first();
            if (! $roleRecord) {
                $roleSlug = 'member';
                $roleRecord = Role::query()->where('slug', 'member')->first();
            }

            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $name ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'is_superadmin' => false,
                ]);
                $createdUsers++;
            }

            $membership = MinistryMembership::query()
                ->where('ministry_id', $ministry->id)
                ->where('user_id', $user->id)
                ->first();

            $sendInvitation = ! $membership;
            if (! $membership) {
                MinistryMembership::create([
                    'ministry_id' => $ministry->id,
                    'user_id' => $user->id,
                    'role' => $roleSlug,
                    'role_id' => $roleRecord?->id,
                ]);
                $addedMemberships++;
            } else {
                $membership->role = $roleSlug;
                $membership->role_id = $roleRecord?->id;
                $membership->save();
                $updatedMemberships++;
            }
            if ($sendInvitation) app(\App\Services\AccountInvitation::class)->send($user, $request->attributes->get('organisation'));
        }

        fclose($handle);

        return response()->json([
            'created_users' => $createdUsers,
            'added_memberships' => $addedMemberships,
            'updated_memberships' => $updatedMemberships,
            'skipped_rows' => $skippedRows,
            'errors' => $errors,
        ]);
    }

    protected function payload(MinistryMembership $membership): array
    {
        return [
            'id' => $membership->user->id,
            'name' => $membership->user->name,
            'email' => $membership->user->email,
            'role' => $membership->roleSlug(),
        ];
    }

    protected function looksLikeHeader(array $row): bool
    {
        $normalized = implode(',', array_map(fn ($value) => strtolower($value), $row));
        return str_contains($normalized, 'email') && str_contains($normalized, 'name');
    }

    protected function normalizeRole(?string $roleValue): string
    {
        $role = strtolower(trim((string) $roleValue));
        if ($role === '') {
            return 'member';
        }
        if (str_contains($role, 'admin')) {
            return 'admin';
        }
        if (str_contains($role, 'leader')) {
            return 'leader';
        }
        return 'member';
    }
}
