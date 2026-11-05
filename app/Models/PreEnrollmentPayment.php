<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreEnrollmentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pre_enrollment_id',
        'cycle_id',
        'doc_type',
        'doc_number',
        'payer_name',
        'career_name',
        'type',
        'related_payment_id',
        'amount',
        'payment_method',
        'concept',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cycle_id' => 'integer',
        ];
    }

    public function preEnrollment(): BelongsTo
    {
        return $this->belongsTo(PreEnrollment::class);
    }

    public function relatedPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'related_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCharge(): bool
    {
        return $this->type === 'charge';
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    public function isRefunded(): bool
    {
        return $this->refunds()->exists();
    }
}
