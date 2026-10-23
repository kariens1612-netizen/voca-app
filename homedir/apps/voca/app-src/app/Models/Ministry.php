<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ministry extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'entity',
        'slug',
        'name',
        'description',
        'whatsapp_number',
        'details',
        'theme',
    ];

    protected $casts = [
        'theme' => 'array',
        'details' => 'array',
    ];

    public function memberships()
    {
        return $this->hasMany(MinistryMembership::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_ministry');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
