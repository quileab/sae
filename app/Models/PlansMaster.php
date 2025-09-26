<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlansMaster extends Model
{
    public function details()
    {
        return $this->hasMany(PlansDetail::class);
    }
}
