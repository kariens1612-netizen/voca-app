<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FinanceSlip extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id',
        'ministry_id',
        'uploaded_by',
        'reviewed_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'supplier',
        'amount',
        'currency',
        'purchased_at',
        'notes',
        'status',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'purchased_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function ministry() { return $this->belongsTo(Ministry::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return url('/storage/'.ltrim($this->path, '/'));
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'ministry' => $this->ministry?->only(['id', 'name', 'slug', 'entity']),
            'uploaded_by' => $this->uploader?->only(['id', 'name', 'email']),
            'reviewed_by' => $this->reviewer?->only(['id', 'name', 'email']),
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'supplier' => $this->supplier,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'purchased_at' => $this->purchased_at?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,
            'url' => $this->url,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
        ];
    }
}
