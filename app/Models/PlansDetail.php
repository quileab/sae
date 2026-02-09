<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlansDetail extends Model
{
    protected $fillable = ['plans_master_id', 'date', 'title', 'amount'];

    protected $casts = [
        'date' => 'date',
    ];

    public function master()
    {
        return $this->belongsTo(PlansMaster::class);
    }
}
