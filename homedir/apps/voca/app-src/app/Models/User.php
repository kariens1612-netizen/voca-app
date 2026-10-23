<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_superadmin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
        ];
    }

    public function canAccessOrganisation(?int $organisationId): bool
    {
        if ($this->is_superadmin) return true;
        if (! $organisationId) return false;
        $membership = $this->organisationMemberships()->where('organisation_id', $organisationId)->first();
        if ($membership) return $membership->status === 'active';
        // Preserve access for legacy ministry accounts without an organisation membership.
        return $this->memberships()->where('organisation_id', $organisationId)->exists();
    }

    public function canManageOrganisation(?int $organisationId): bool
    {
        return $this->is_superadmin || ($organisationId && $this->organisationMemberships()
            ->where('organisation_id', $organisationId)->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists());
    }

    public function memberships()
    {
        return $this->hasMany(MinistryMembership::class);
    }

    public function organisationMemberships()
    {
        return $this->hasMany(OrganisationMembership::class);
    }

    public function organisations()
    {
        return $this->belongsToMany(Organisation::class, 'organisation_memberships')
            ->withPivot(['role', 'status'])->withTimestamps();
    }

    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }
}
