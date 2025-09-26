<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlansDetail extends Model
{
    public function master()
    {
        return $this->belongsTo(PlansMaster::class);
    }
}
