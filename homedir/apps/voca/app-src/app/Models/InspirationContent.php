<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InspirationContent extends Model
{
    use BelongsToOrganisation;
    public const TYPES = ['scripture', 'newsletter', 'prophetic_word', 'encouragement', 'video'];
    public const STATUSES = ['draft', 'scheduled', 'published', 'archived'];
    public const AUDIENCES = ['public', 'members'];

    protected $fillable = [
        'organisation_id',
        'ministry_id', 'created_by', 'type', 'title', 'slug', 'summary', 'body',
        'author', 'scripture_reference', 'scripture_translation', 'audience',
        'status', 'is_featured', 'publish_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function ministry() { return $this->belongsTo(Ministry::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function media() { return $this->hasMany(InspirationMedia::class); }

    public function scopeVisibleNow(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', 'published')
                ->orWhere(function (Builder $query) {
                    $query->where('status', 'scheduled')->where('publish_at', '<=', now());
                });
        });
    }

    public function isVisibleNow(): bool
    {
        return $this->status === 'published'
            || ($this->status === 'scheduled' && $this->publish_at?->isPast());
    }
}
