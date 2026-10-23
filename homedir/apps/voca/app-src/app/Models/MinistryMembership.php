<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinistryMembership extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'user_id',
        'ministry_id',
        'role_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }

    public function roleRecord()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roleSlug(): ?string
    {
        return $this->roleRecord?->slug ?? $this->role;
    }
}
