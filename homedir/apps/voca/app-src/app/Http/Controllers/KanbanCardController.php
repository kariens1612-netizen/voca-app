<?php

namespace App\Http\Controllers;

use App\Models\KanbanCard;
use App\Models\Ministry;
use Illuminate\Http\Request;

class KanbanCardController extends Controller
{
    protected array $allowedStatuses = ['todo', 'doing', 'done'];

    public function index(Request $request, Ministry $ministry)
    {
        $user = $request->user();

        $query = KanbanCard::query()
            ->with(['assignedUser:id,name,email', 'comments.user:id,name'])
            ->where('ministry_id', $ministry->id)
            ->orderBy('created_at');

        if (! $this->canManageAll($user, $ministry)) {
            $query->where('assigned_user_id', $user->id);
        }

        return response()->json([
            'cards' => $query->get()->map(fn (KanbanCard $card) => $this->cardPayload($card)),
        ]);
    }

    public function store(Request $request, Ministry $ministry)
    {
        $user = $request->user();

        if (! $this->canManageAll($user, $ministry)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $status = $data['status'] ?? 'todo';
        if (! in_array($status, $this->allowedStatuses, true)) {
            return response()->json(['message' => 'Invalid status.'], 422);
        }

        if (! empty($data['assigned_user_id']) && ! $this->userInMinistry($data['assigned_user_id'], $ministry->id)) {
            return response()->json(['message' => 'User is not part of this ministry.'], 422);
        }

        $card = KanbanCard::create([
            'ministry_id' => $ministry->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $status,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'created_by_id' => $user->id,
        ]);

        $card->load(['assignedUser:id,name,email', 'comments.user:id,name']);

        return response()->json($this->cardPayload($card), 201);
    }

    public function update(Request $request, Ministry $ministry, KanbanCard $card)
    {
        if ($card->ministry_id !== $ministry->id) {
            return response()->json(['message' => 'Card not found.'], 404);
        }

        $user = $request->user();
        if (! $this->canManageAll($user, $ministry)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (array_key_exists('status', $data) && ! in_array($data['status'], $this->allowedStatuses, true)) {
            return response()->json(['message' => 'Invalid status.'], 422);
        }

        if (array_key_exists('assigned_user_id', $data) && ! empty($data['assigned_user_id'])) {
            if (! $this->userInMinistry($data['assigned_user_id'], $ministry->id)) {
                return response()->json(['message' => 'User is not part of this ministry.'], 422);
            }
        }

        $card->fill($data);
        $card->save();

        $card->load(['assignedUser:id,name,email', 'comments.user:id,name']);

        return response()->json($this->cardPayload($card));
    }

    protected function cardPayload(KanbanCard $card): array
    {
        return [
            'id' => $card->id,
            'ministry_id' => $card->ministry_id,
            'title' => $card->title,
            'description' => $card->description,
            'status' => $card->status,
            'assigned_user' => $card->assignedUser
                ? [
                    'id' => $card->assignedUser->id,
                    'name' => $card->assignedUser->name,
                    'email' => $card->assignedUser->email,
                ]
                : null,
            'created_by_id' => $card->created_by_id,
            'comments' => $card->comments
                ->sortBy('created_at')
                ->map(fn ($comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                    ],
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])
                ->values(),
            'created_at' => $card->created_at?->toIso8601String(),
            'updated_at' => $card->updated_at?->toIso8601String(),
        ];
    }

    protected function canManageAll($user, Ministry $ministry): bool
    {
        if ($user?->is_superadmin) {
            return true;
        }

        $membership = $user?->memberships()
            ->where('ministry_id', $ministry->id)
            ->with('roleRecord')
            ->first();

        return $membership?->roleSlug() === 'admin';
    }

    protected function userInMinistry(int $userId, int $ministryId): bool
    {
        return \App\Models\MinistryMembership::query()
            ->where('user_id', $userId)
            ->where('ministry_id', $ministryId)
            ->exists();
    }
}
