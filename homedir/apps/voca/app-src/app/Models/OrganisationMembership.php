<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganisationMembership extends Model
{
    protected $fillable = ['organisation_id', 'user_id', 'role', 'status'];
    public function organisation() { return $this->belongsTo(Organisation::class); }
    public function user() { return $this->belongsTo(User::class); }
}
