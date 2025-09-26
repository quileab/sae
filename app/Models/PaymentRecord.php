<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'userpayments_id',
        'paymentBox',
        'description',
        'paymentAmount',
    ];

    protected $table = 'paymentrecords';

    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userpayments()
    {
        return $this->belongsTo(UserPayments::class);
    }
}
