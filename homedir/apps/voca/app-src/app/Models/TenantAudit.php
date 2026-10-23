<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantAudit extends Model
{
    public $timestamps = false;
    protected $fillable = ['organisation_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip_address', 'created_at'];
    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
}
