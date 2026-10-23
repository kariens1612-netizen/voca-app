<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = ['organisation_id', 'user_id', 'locale', 'timezone', 'notifications', 'dashboard', 'ministry_subscriptions'];
    protected $casts = ['notifications' => 'array', 'dashboard' => 'array', 'ministry_subscriptions' => 'array'];
}
