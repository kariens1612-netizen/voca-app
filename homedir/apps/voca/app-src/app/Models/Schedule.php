<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'ministry_id',
        'title',
        'date',
        'start_time',
        'end_time',
        'location',
        'thumbnail_url',
        'notes',
        'is_recurring',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_byweekday',
        'recurrence_until',
    ];

    protected $casts = [
        'date' => 'date',
        'recurrence_until' => 'date',
        'is_recurring' => 'boolean',
        'recurrence_byweekday' => 'array',
    ];

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'schedule_assignments')->withTimestamps();
    }

    public function audits()
    {
        return $this->hasMany(ScheduleAudit::class);
    }
}
