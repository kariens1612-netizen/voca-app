<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class MovementJourney extends Model
{
    use BelongsToOrganisation;

    protected $table = "movement_journeys";
    protected $guarded = ["id"];
    protected $casts = ["expected_ids" => "array"];
}
