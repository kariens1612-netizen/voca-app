<?php

namespace App\Http\Controllers;

use App\Mail\AnnouncementPosted;
use App\Models\Announcement;
use App\Models\Ministry;
use App\Models\MinistryMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AnnouncementController extends Controller
{
    public function index(Request $request, Ministry $ministry)
    {
        $announcements = Announcement::query()
            ->where(function ($query) use ($ministry) {
                $query->where('is_global', true)
                    ->where(function ($query) use ($ministry) {
                        $query->whereHas('ministries', function ($subQuery) use ($ministry) {
                            $subQuery->where('ministries.id', $ministry->id);
                        })
                            ->orWhereDoesntHave('ministries');
                    })
                    ->where(function ($query) use ($ministry) {
                        $query->whereNull('entity')
                            ->orWhere('entity', $ministry->entity);
                    });
            })
            ->orWhere(function ($query) use ($ministry) {
                $query->where('is_global', false)
                    ->whereHas('ministries', function ($subQuery) use ($ministry) {
                        $subQuery->where('ministries.id', $ministry->id);
                    });
            })
            ->with([
                'author:id,name',
                'ministries:id,slug,name',
            ])
            ->orderByDesc('pinned')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Announcement $announcement) => $this->payload($announcement))
            ->values();

        return response()->json(['announcements' => $announcements]);
    }

    public function store(Request $request, Ministry $ministry)
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'pinned' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:2'],
            'target_all' => ['nullable', 'boolean'],
            'ministry_ids' => ['nullable', 'array'],
            'ministry_ids.*' => ['integer', 'exists:ministries,id'],
        ]);

        $isGlobal = $user?->is_superadmin ? true : false;
        $targetAll = $user?->is_superadmin ? (bool) ($data['target_all'] ?? false) : false;
        $pinned = (bool) ($data['pinned'] ?? false);
        $priority = (int) ($data['priority'] ?? 0);

        if ($isGlobal && ! $targetAll) {
            $selected = $data['ministry_ids'] ?? [];
            if (empty($selected)) {
                return response()->json(['message' => 'Select at least one ministry.'], 422);
            }
            $selectedIds = $data['ministry_ids'] ?? [];
            $allowedIds = Ministry::query()
                ->where('entity', $ministry->entity)
                ->whereIn('id', $selectedIds)
                ->pluck('id')
                ->toArray();

            if (count($allowedIds) !== count($selectedIds)) {
                return response()->json(['message' => 'Invalid ministry selection.'], 422);
            }

            $data['ministry_ids'] = $allowedIds;
        }

        $announcement = Announcement::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'entity' => $ministry->entity,
            'is_global' => $isGlobal,
            'pinned' => $pinned,
            'priority' => $priority,
            'created_by' => $user?->id,
        ]);

        if ($isGlobal) {
            if (! $targetAll) {
                $announcement->ministries()->sync($this->tenantPivot($data['ministry_ids'] ?? []));
            }
        } else {
            $announcement->ministries()->sync($this->tenantPivot([$ministry->id]));
        }

        $announcement->load([
            'author:id,name',
            'ministries:id,slug,name',
        ]);

        $this->sendEmailNotifications($announcement, $ministry);

        return response()->json($this->payload($announcement));
    }

    public function update(Request $request, Ministry $ministry, Announcement $announcement)
    {
        $user = $request->user();

        if ($announcement->entity && $announcement->entity !== $ministry->entity) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        if (! $this->canManage($user, $ministry, $announcement)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'pinned' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:2'],
            'target_all' => ['nullable', 'boolean'],
            'ministry_ids' => ['nullable', 'array'],
            'ministry_ids.*' => ['integer', 'exists:ministries,id'],
        ]);

        $isGlobal = $announcement->is_global;
        $targetAll = $announcement->is_global && $announcement->ministries->isEmpty();

        if ($user?->is_superadmin) {
            $targetAll = (bool) ($data['target_all'] ?? $targetAll);
            if ($announcement->is_global && ! $targetAll) {
                $selected = $data['ministry_ids'] ?? [];
                if (empty($selected)) {
                    return response()->json(['message' => 'Select at least one ministry.'], 422);
                }

                $allowedIds = Ministry::query()
                    ->where('entity', $ministry->entity)
                    ->whereIn('id', $selected)
                    ->pluck('id')
                    ->toArray();

                if (count($allowedIds) !== count($selected)) {
                    return response()->json(['message' => 'Invalid ministry selection.'], 422);
                }

                $announcement->ministries()->sync($this->tenantPivot($allowedIds));
            } else {
                $announcement->ministries()->sync([]);
            }
        }

        $announcement->update([
            'title' => $data['title'],
            'body' => $data['body'],
            'pinned' => (bool) ($data['pinned'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_global' => $isGlobal,
        ]);

        $announcement->load([
            'author:id,name',
            'ministries:id,slug,name',
        ]);

        return response()->json($this->payload($announcement));
    }

    private function tenantPivot(array $ids): array
    {
        $organisationId = app(\App\Tenancy\CurrentOrganisation::class)->id();
        return collect($ids)->mapWithKeys(fn ($id) => [$id => ['organisation_id' => $organisationId]])->all();
    }

    public function destroy(Request $request, Ministry $ministry, Announcement $announcement)
    {
        $user = $request->user();

        if ($announcement->entity && $announcement->entity !== $ministry->entity) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        if (! $this->canManage($user, $ministry, $announcement)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $announcement->delete();

        return response()->noContent();
    }

    protected function payload(Announcement $announcement): array
    {
        $targetMinistries = $announcement->ministries->map(fn ($ministry) => [
            'id' => $ministry->id,
            'slug' => $ministry->slug,
            'name' => $ministry->name,
        ])->values();

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'entity' => $announcement->entity,
            'is_global' => $announcement->is_global,
            'pinned' => $announcement->pinned,
            'priority' => $announcement->priority,
            'target_all' => $announcement->is_global && $targetMinistries->isEmpty(),
            'target_ministries' => $targetMinistries,
            'created_at' => $announcement->created_at?->toDateTimeString(),
            'sent_at' => $announcement->sent_at?->toDateTimeString(),
            'author' => $announcement->author
                ? [
                    'id' => $announcement->author->id,
                    'name' => $announcement->author->name,
                ]
                : null,
        ];
    }

    protected function canManage($user, Ministry $ministry, Announcement $announcement): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        if ($announcement->is_global) {
            return false;
        }

        $membership = $user->memberships()
            ->where('ministry_id', $ministry->id)
            ->with('roleRecord')
            ->first();

        if (! $membership || ! in_array($membership->roleSlug(), ['admin', 'leader'], true)) {
            return false;
        }

        return $announcement->ministries()->where('ministries.id', $ministry->id)->exists();
    }

    protected function sendEmailNotifications(Announcement $announcement, Ministry $ministry): void
    {
        $ministryIds = [];

        if ($announcement->is_global) {
            if ($announcement->ministries->isNotEmpty()) {
                $ministryIds = $announcement->ministries->pluck('id')->toArray();
            } else {
                $ministryIds = Ministry::query()
                    ->where('entity', $ministry->entity)
                    ->pluck('id')
                    ->toArray();
            }
        } else {
            $ministryIds = [$ministry->id];
        }

        if (empty($ministryIds)) {
            return;
        }

        $recipients = MinistryMembership::query()
            ->whereIn('ministry_id', $ministryIds)
            ->with('user:id,email')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $announcement->sent_at = now();
        $announcement->save();

        $mailMinistry = $announcement->is_global ? null : $ministry;

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new AnnouncementPosted($announcement, $mailMinistry));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
