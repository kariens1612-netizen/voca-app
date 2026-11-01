<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class MovementRoster extends Model
{
    use BelongsToOrganisation;

    protected $table = "movement_rosters";
    protected $guarded = ["id"];
    protected $casts = [];
}
