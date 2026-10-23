<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganisationDomain extends Model
{
    protected $fillable = ['organisation_id', 'host', 'is_primary', 'verified_at', 'verification_token'];
    protected $casts = ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    protected $hidden = ['verification_token'];
    public function organisation() { return $this->belongsTo(Organisation::class); }
}
