<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class MovementScan extends Model
{
    use BelongsToOrganisation;

    protected $table = "movement_scans";
    protected $guarded = ["id"];
    protected $casts = ["recorded_at" => "datetime", "received_at" => "datetime"];
    public $timestamps = false;
}
