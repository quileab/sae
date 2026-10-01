<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentInvoice extends Model
{
    protected $fillable = [
        'voucher_type',
        'point_of_sales',
        'voucher_number',
        'cae',
        'cae_expiration',
        'afip_response',
    ];

    protected function casts(): array
    {
        return [
            'afip_response' => 'array',
        ];
    }

    public function paymentRecords()
    {
        return $this->hasMany(PaymentRecord::class);
    }
}
