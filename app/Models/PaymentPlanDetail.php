<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlanDetail extends Model
{
    protected $table = 'plans_details';

    protected $fillable = ['plans_master_id', 'date', 'title', 'amount'];

    protected $casts = [
        'date' => 'date',
    ];

    public function master()
    {
        return $this->belongsTo(PaymentPlan::class, 'plans_master_id');
    }
}
