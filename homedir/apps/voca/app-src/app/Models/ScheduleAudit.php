<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleAudit extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'ministry_id',
        'schedule_id',
        'actor_id',
        'action',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
