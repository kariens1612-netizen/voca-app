<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveStreamSetting extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'platform',
        'status',
        'schedule',
        'embed_url',
        'watch_url',
        'stream_title',
        'notes',
        'api_key',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
