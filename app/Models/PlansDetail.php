<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlansDetail extends Model
{
    protected $fillable = ['plans_master_id', 'date', 'title', 'amount'];

    public function master()
    {
        return $this->belongsTo(PlansMaster::class);
    }
}
