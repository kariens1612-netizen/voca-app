<?php

namespace App\Http\Controllers;

use App\Models\InspirationContent;
use App\Models\Ministry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InspirationContentController extends Controller
{
    public function publicIndex(Request $request)
    {
        return $this->listing($request, InspirationContent::query()
            ->visibleNow()->where('audience', 'public'));
    }

    public function memberIndex(Request $request)
    {
        abort_unless($request->user(), 401);
        $ministryIds = $request->user()->is_superadmin
            ? Ministry::query()->pluck('id')
            : $request->user()->memberships()->pluck('ministry_id');

        return $this->listing($request, InspirationContent::query()
            ->visibleNow()->whereIn('ministry_id', $ministryIds));
    }

    public function show(Request $request, InspirationContent $content)
    {
        abort_unless($this->canView($request, $content), 404);
        return response()->json($this->payload($content->load(['ministry', 'media'])));
    }

    public function adminIndex(Request $request)
    {
        abort_unless($request->user(), 401);
        $query = InspirationContent::query()->with(['ministry', 'media'])
            ->when(! $request->user()->is_superadmin, function (Builder $query) use ($request) {
                $query->whereIn('ministry_id', $request->user()->memberships()
                    ->where(function (Builder $membership) {
                        $membership->where('role', 'admin')
                            ->orWhereHas('roleRecord', fn (Builder $role) => $role->where('slug', 'admin'));
                    })
                    ->pluck('ministry_id'));
            });

        foreach (['type', 'status', 'audience', 'ministry_id'] as $filter) {
            $query->when($request->filled($filter), fn (Builder $q) => $q->where($filter, $request->input($filter)));
        }
        $query->when($request->filled('search'), fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner->where('title', 'like', '%'.$request->search.'%')
                ->orWhere('summary', 'like', '%'.$request->search.'%')
        ));

        return response()->json($query->latest()->paginate(15)->through(fn ($item) => $this->payload($item)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->authorizeManage($request, (int) $data['ministry_id']);
        $data['created_by'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['body'] = $this->sanitize($data['body'] ?? null);
        $content = InspirationContent::create($data);
        $this->syncUploads($request, $content);

        return response()->json($this->payload($content->load(['ministry', 'media'])), 201);
    }

    public function update(Request $request, InspirationContent $content)
    {
        $this->authorizeManage($request, $content->ministry_id);
        $data = $this->validated($request, true);
        if (isset($data['ministry_id']) && (int) $data['ministry_id'] !== $content->ministry_id) {
            abort_unless($request->user()->is_superadmin, 403);
        }
        if (isset($data['title']) && $data['title'] !== $content->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $content->id);
        }
        if (array_key_exists('body', $data)) {
            $data['body'] = $this->sanitize($data['body']);
        }
        $content->update($data);
        $this->syncUploads($request, $content);

        return response()->json($this->payload($content->fresh(['ministry', 'media'])));
    }

    public function duplicate(Request $request, InspirationContent $content)
    {
        $this->authorizeManage($request, $content->ministry_id);
        $copy = $content->replicate(['slug', 'status', 'publish_at', 'published_at']);
        $copy->title = $content->title.' (Copy)';
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->status = 'draft';
        $copy->created_by = $request->user()->id;
        $copy->save();
        foreach ($content->media as $media) {
            $copy->media()->create($media->only(['kind', 'disk', 'path', 'original_name', 'mime_type', 'size', 'external_url']));
        }
        return response()->json($this->payload($copy->load(['ministry', 'media'])), 201);
    }

    public function transition(Request $request, InspirationContent $content, string $action)
    {
        $this->authorizeManage($request, $content->ministry_id);
        abort_unless(in_array($action, ['publish', 'unpublish', 'archive'], true), 404);
        if ($action === 'publish') {
            $content->update(['status' => 'published', 'published_at' => now(), 'publish_at' => null]);
        } elseif ($action === 'unpublish') {
            $content->update(['status' => 'draft', 'published_at' => null, 'publish_at' => null]);
        } else {
            $content->update(['status' => 'archived']);
        }
        return response()->json($this->payload($content->fresh(['ministry', 'media'])));
    }

    public function destroy(Request $request, InspirationContent $content)
    {
        $this->authorizeManage($request, $content->ministry_id);
        foreach ($content->media as $media) {
            if ($media->path && $content->media()->where('path', $media->path)->count() === 1) {
                Storage::disk($media->disk)->delete($media->path);
            }
        }
        $content->delete();
        return response()->noContent();
    }

    private function listing(Request $request, Builder $query)
    {
        $query->with(['ministry', 'media'])
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->type))
            ->when($request->filled('search'), fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->where('title', 'like', '%'.$request->search.'%')
                    ->orWhere('summary', 'like', '%'.$request->search.'%')
            ))
            ->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('publish_at');
        return response()->json($query->paginate(12)->through(fn ($item) => $this->payload($item)));
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : ['required'];
        $data = $request->validate([
            'ministry_id' => [...$sometimes, 'integer', 'exists:ministries,id'],
            'type' => [...$sometimes, Rule::in(InspirationContent::TYPES)],
            'title' => [...$sometimes, 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'author' => ['nullable', 'string', 'max:255'],
            'scripture_reference' => ['nullable', 'string', 'max:255'],
            'scripture_translation' => ['nullable', 'string', 'max:30'],
            'audience' => [...$sometimes, Rule::in(InspirationContent::AUDIENCES)],
            'status' => [...$sometimes, Rule::in(InspirationContent::STATUSES)],
            'is_featured' => ['sometimes', 'boolean'],
            'publish_at' => ['nullable', 'date', Rule::requiredIf($request->input('status') === 'scheduled')],
            'external_video_url' => ['nullable', 'url', function ($attribute, $value, $fail) {
                $host = strtolower(parse_url($value, PHP_URL_HOST) ?: '');
                if (! Str::contains($host, ['youtube.com', 'youtu.be', 'vimeo.com', 'rumble.com'])) {
                    $fail('Only YouTube, Vimeo, and Rumble video links are supported.');
                }
            }],
            'cover' => ['nullable', 'image', 'max:'.config('inspiration.image_max_kb')],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.config('inspiration.pdf_max_kb')],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.config('inspiration.video_max_kb')],
        ]);
        if (($data['type'] ?? null) === 'newsletter' && ! $partial
            && empty($data['body']) && ! $request->hasFile('pdf')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'A newsletter needs web content, a PDF, or both.',
            ]);
        }
        if (($data['type'] ?? null) === 'video' && ! $partial
            && empty($data['external_video_url']) && ! $request->hasFile('video')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'video' => 'Add a video upload or a supported video link.',
            ]);
        }
        return $data;
    }

    private function authorizeManage(Request $request, int $ministryId): void
    {
        abort_unless($request->user(), 401);
        if ($request->user()->is_superadmin) return;
        $allowed = $request->user()->memberships()->where('ministry_id', $ministryId)
            ->where(function (Builder $query) {
                $query->where('role', 'admin')
                    ->orWhereHas('roleRecord', fn (Builder $role) => $role->where('slug', 'admin'));
            })->exists();
        abort_unless($allowed, 403);
    }

    private function canView(Request $request, InspirationContent $content): bool
    {
        if (! $content->isVisibleNow()) return false;
        if ($content->audience === 'public') return true;
        $user = $request->user();
        return $user && ($user->is_superadmin || $user->memberships()->where('ministry_id', $content->ministry_id)->exists());
    }

    private function syncUploads(Request $request, InspirationContent $content): void
    {
        $files = ['cover' => 'cover', 'pdf' => 'pdf', 'video' => 'video'];
        foreach ($files as $input => $kind) {
            if (! $request->hasFile($input)) continue;
            $file = $request->file($input);
            $old = $content->media()->where('kind', $kind)->first();
            $path = $file->store("inspiration/{$content->id}", 'local');
            $content->media()->updateOrCreate(['kind' => $kind], [
                'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'external_url' => null,
            ]);
            if ($old?->path) Storage::disk($old->disk)->delete($old->path);
        }
        if ($request->filled('external_video_url')) {
            $old = $content->media()->where('kind', 'video')->first();
            if ($old?->path) Storage::disk($old->disk)->delete($old->path);
            $content->media()->updateOrCreate(['kind' => 'video'], [
                'disk' => 'local', 'path' => null, 'original_name' => null, 'mime_type' => null,
                'size' => null, 'external_url' => $request->external_video_url,
            ]);
        }
    }

    private function payload(InspirationContent $content): array
    {
        $content->loadMissing(['ministry', 'media']);
        $media = $content->media->mapWithKeys(fn ($item) => [$item->kind => [
            'id' => $item->id, 'name' => $item->original_name, 'mime_type' => $item->mime_type,
            'size' => $item->size, 'url' => route('inspiration.media', $item),
            'external_url' => $item->external_url, 'embed_url' => $this->embedUrl($item->external_url),
        ]]);
        return array_merge($content->only([
            'id', 'ministry_id', 'type', 'title', 'slug', 'summary', 'body', 'author',
            'scripture_reference', 'scripture_translation', 'audience', 'status',
            'is_featured', 'publish_at', 'published_at', 'created_at', 'updated_at',
        ]), ['ministry' => $content->ministry?->only(['id', 'name', 'slug']), 'media' => $media]);
    }

    private function embedUrl(?string $url): ?string
    {
        if (! $url) return null;
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        if (Str::contains($host, 'youtu.be')) return 'https://www.youtube.com/embed/'.explode('/', $path)[0];
        if (Str::contains($host, 'youtube.com') && ! empty($query['v'])) return 'https://www.youtube.com/embed/'.$query['v'];
        if (Str::contains($host, 'vimeo.com')) return 'https://player.vimeo.com/video/'.explode('/', $path)[0];
        if (Str::contains($host, 'rumble.com')) return $url;
        return null;
    }

    private function uniqueSlug(string $title, ?int $except = null): string
    {
        $base = Str::slug($title) ?: 'inspiration';
        $slug = $base; $suffix = 2;
        while (InspirationContent::where('slug', $slug)->when($except, fn ($q) => $q->whereKeyNot($except))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }
        return $slug;
    }

    private function sanitize(?string $html): ?string
    {
        if (! $html) return $html;
        $html = preg_replace('/<(script|style|iframe|object|embed)[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/is', '', $html);
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*javascript:.*?\2/is', '', $html);
        return strip_tags($html, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a>');
    }
}
