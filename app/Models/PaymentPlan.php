<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    protected $table = 'plans_masters';

    protected $fillable = ['title'];

    public function details()
    {
        return $this->hasMany(PaymentPlanDetail::class, 'plans_master_id');
    }
}
