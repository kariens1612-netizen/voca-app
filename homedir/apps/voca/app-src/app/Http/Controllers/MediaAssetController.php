<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Services\TenantAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MediaAssetController extends Controller
{
    private const MAX_UPLOAD_KB = 8192;

    public function __construct(private TenantAuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorizeTenantAccess($request);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['image', 'file'])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $assets = MediaAsset::query()
            ->with('uploader:id,name,email')
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('original_name', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(24)
            ->through(fn (MediaAsset $asset) => $asset->toLibraryPayload());

        return response()->json(['data' => $assets]);
    }

    public function store(Request $request)
    {
        $this->authorizeTenantAccess($request);

        $data = $request->validate([
            'asset' => [
                'required',
                'file',
                'max:'.self::MAX_UPLOAD_KB,
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
            ],
        ]);

        $organisation = $request->attributes->get('organisation');
        $file = $data['asset'];
        $mimeType = $file->getMimeType();
        $type = str_starts_with((string) $mimeType, 'image/') ? 'image' : 'file';
        $path = $file->store("media/{$organisation->id}", 'public');

        $asset = MediaAsset::create([
            'organisation_id' => $organisation->id,
            'uploaded_by' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'type' => $type,
        ])->load('uploader:id,name,email');

        $this->audit->log($request, 'media.uploaded', $asset, null, $asset->toArray());

        return response()->json(['data' => $asset->toLibraryPayload()], 201);
    }

    public function show(MediaAsset $mediaAsset)
    {
        abort_unless(Storage::disk($mediaAsset->disk)->exists($mediaAsset->path), 404);

        return Storage::disk($mediaAsset->disk)->response(
            $mediaAsset->path,
            $mediaAsset->original_name,
            ['Content-Type' => $mediaAsset->mime_type, 'X-Content-Type-Options' => 'nosniff'],
            'inline'
        );
    }

    public function destroy(Request $request, MediaAsset $mediaAsset)
    {
        abort_unless($mediaAsset->organisation_id === $request->attributes->get('organisation')->id, 404);

        $before = $mediaAsset->toArray();
        Storage::disk($mediaAsset->disk)->delete($mediaAsset->path);
        $mediaAsset->delete();

        $this->audit->log($request, 'media.deleted', $mediaAsset, $before, null);

        return response()->noContent();
    }

    private function authorizeTenantAccess(Request $request): void
    {
        $user = $request->user();
        $organisation = $request->attributes->get('organisation');

        abort_unless($user, 401);
        if ($user->is_superadmin) return;

        abort_unless($user->organisationMemberships()
            ->where('organisation_id', $organisation?->id)
            ->where('status', 'active')
            ->exists(), 403);
    }
}
