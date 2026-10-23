<?php

namespace App\Http\Controllers;

use App\Models\InspirationMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InspirationMediaController extends Controller
{
    public function show(Request $request, InspirationMedia $media)
    {
        $content = $media->content;
        $allowed = $content->isVisibleNow() && $content->audience === 'public';
        if (! $allowed && $request->user()) {
            $allowed = $request->user()->is_superadmin
                || $request->user()->memberships()->where('ministry_id', $content->ministry_id)->exists();
        }
        abort_unless($allowed && $media->path && Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response(
            $media->path,
            $media->original_name,
            ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff'],
            'inline'
        );
    }
}
