<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'type',
    ];

    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'public' && $this->exists) {
            $organisation = Organisation::find($this->organisation_id);
            if ($organisation && $organisation->plan === 'standard' && $organisation->slug !== config('tenancy.default_slug')) {
                return $organisation->portalUrl().'/media/'.$this->id;
            }
            return route('media.assets.show', $this);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function toLibraryPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'type' => $this->type,
            'url' => $this->url,
            'uploaded_by' => $this->uploader?->only(['id', 'name', 'email']),
            'created_at' => $this->created_at,
        ];
    }
}
