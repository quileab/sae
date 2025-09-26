<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPayments extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'title',
        'paid',
        'amount',
    ];

    protected $table = 'userpayments';

    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentrecords()
    {
        return $this->hasMany(PaymentRecord::class);
    }
}
