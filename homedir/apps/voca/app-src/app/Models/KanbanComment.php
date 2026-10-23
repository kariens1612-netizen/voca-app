<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KanbanComment extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'kanban_card_id',
        'user_id',
        'body',
    ];

    public function card()
    {
        return $this->belongsTo(KanbanCard::class, 'kanban_card_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
