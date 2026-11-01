<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class MovementDelegate extends Model
{
    use BelongsToOrganisation;

    protected $table = "movement_delegates";
    protected $guarded = ["id"];
    protected $casts = [];
}
