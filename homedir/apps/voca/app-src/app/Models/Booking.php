<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id',
        'reference',
        'name',
        'email',
        'phone',
        'ministry',
        'appointment_date',
        'appointment_time',
        'notes',
        'amount',
        'status',
        'payment_reference',
        'payment_provider',
        'payment_transaction_id',
        'gateway_status',
        'paid_at',
        'payment_payload',
        'ozow_request_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_payload' => 'array',
        ];
    }
}
