<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'title',
        'body',
        'entity',
        'is_global',
        'pinned',
        'priority',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'pinned' => 'boolean',
        'priority' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function ministries()
    {
        return $this->belongsToMany(Ministry::class, 'announcement_ministry');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
