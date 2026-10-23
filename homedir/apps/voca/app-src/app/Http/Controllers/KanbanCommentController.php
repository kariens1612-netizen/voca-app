<?php

namespace App\Http\Controllers;

use App\Models\KanbanCard;
use App\Models\Ministry;
use Illuminate\Http\Request;

class KanbanCommentController extends Controller
{
    public function store(Request $request, Ministry $ministry, KanbanCard $card)
    {
        if ($card->ministry_id !== $ministry->id) {
            return response()->json(['message' => 'Card not found.'], 404);
        }

        $user = $request->user();
        if (! $this->canComment($user, $ministry, $card)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $card->comments()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
            ],
            'created_at' => $comment->created_at?->toIso8601String(),
        ], 201);
    }

    protected function canComment($user, Ministry $ministry, KanbanCard $card): bool
    {
        if ($user?->is_superadmin) {
            return true;
        }

        $membership = $user?->memberships()
            ->where('ministry_id', $ministry->id)
            ->with('roleRecord')
            ->first();

        if ($membership?->roleSlug() === 'admin') {
            return true;
        }

        return $card->assigned_user_id === $user?->id;
    }
}
