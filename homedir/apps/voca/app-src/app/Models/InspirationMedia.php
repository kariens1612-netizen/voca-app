<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class InspirationMedia extends Model
{
    use BelongsToOrganisation;
    protected $table = 'inspiration_media';
    protected $fillable = [
        'organisation_id',
        'inspiration_content_id', 'kind', 'disk', 'path', 'original_name',
        'mime_type', 'size', 'external_url',
    ];

    public function content() { return $this->belongsTo(InspirationContent::class, 'inspiration_content_id'); }
}
