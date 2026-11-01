<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    public const DEFAULT_MODULES = [
        'livestreaming' => true, 'events' => true, 'inspiration' => true,
        'schedules' => true, 'announcements' => true, 'kanban' => true,
        'bookings' => true, 'public_ministries' => true, 'commerce' => true,
        'finance' => true,
    ];

    protected $fillable = ['name', 'slug', 'status', 'plan', 'storage_limit_bytes', 'branding', 'settings', 'modules', 'archived_at'];
    protected $casts = ['branding' => 'array', 'settings' => 'array', 'modules' => 'array', 'archived_at' => 'datetime'];

    public function domains() { return $this->hasMany(OrganisationDomain::class); }
    public function memberships() { return $this->hasMany(OrganisationMembership::class); }
    public function users() { return $this->belongsToMany(User::class, 'organisation_memberships')->withPivot(['role', 'status'])->withTimestamps(); }
    public function ministries() { return $this->hasMany(Ministry::class); }
    public function mediaAssets() { return $this->hasMany(MediaAsset::class); }
    public function financeSlips() { return $this->hasMany(FinanceSlip::class); }

    public function moduleEnabled(string $module): bool
    {
        return (bool) array_merge(self::DEFAULT_MODULES, $this->modules ?? [])[$module] ?? false;
    }

    public function portalUrl(): string
    {
        if ($this->slug === config('tenancy.default_slug')) return rtrim(config('app.url'), '/');
        if ($this->plan === 'standard') return rtrim(config('app.url'), '/').'/'.$this->slug;
        $host = $this->plan === 'enterprise' ? $this->domains()->whereNotNull('verified_at')->orderByDesc('is_primary')->value('host') : null;
        return 'https://'.($host ?: $this->slug.'.'.config('tenancy.base_domain'));
    }

    public function publicPayload(): array
    {
        $branding = array_merge([
            'name' => $this->name, 'logo_url' => null, 'favicon_url' => null,
            'primary_colour' => '#bd1622', 'secondary_colour' => '#111827',
            'font_family' => 'Inter', 'contact' => [], 'navigation' => [], 'legal_links' => [],
        ], $this->branding ?? []);

        return [
            'id' => $this->id, 'slug' => $this->slug, 'name' => $this->name,
            'branding' => $branding,
            'modules' => array_merge(self::DEFAULT_MODULES, $this->modules ?? []),
            'locale' => data_get($this->settings, 'locale', 'en'),
            'timezone' => data_get($this->settings, 'timezone', 'Africa/Johannesburg'),
        ];
    }
}
