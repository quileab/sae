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

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'paid' => 'decimal:2',
    ];

    public function getBgColorAttribute(): string
    {
        if ($this->paid >= $this->amount) {
            return 'bg-green-700';
        }

        if ($this->paid > 0) {
            return 'bg-amber-600';
        }

        return 'bg-blue-700';
    }

    public function getTextColorAttribute(): string
    {
        if ($this->paid >= $this->amount) {
            return 'text-green-200';
        }

        if ($this->paid > 0) {
            return 'text-amber-200';
        }

        return 'text-blue-200';
    }

    public function getBorderColorAttribute(): string
    {
        if ($this->paid >= $this->amount) {
            return 'border-green-500';
        }

        if ($this->paid > 0) {
            return 'border-amber-500';
        }

        return 'border-blue-500';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentrecords()
    {
        return $this->hasMany(PaymentRecord::class);
    }
}
