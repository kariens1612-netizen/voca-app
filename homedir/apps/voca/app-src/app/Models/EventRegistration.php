<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'occurrence_id',
        'schedule_id',
        'event_title',
        'event_date',
        'event_start_time',
        'event_location',
        'name',
        'email',
        'phone',
        'party_size',
        'reference',
        'ticket_token_hash',
        'checked_in_at',
        'checked_in_by',
    ];

    protected $hidden = ['ticket_token_hash'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date:Y-m-d',
            'checked_in_at' => 'datetime',
            'party_size' => 'integer',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
