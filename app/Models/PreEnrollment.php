<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_id', 'career_id', 'user_id', 'doc_type', 'doc_number',
        'email', 'phone', 'payload', 'status', 'source', 'migrated_from_sheets',
        'reviewed_by', 'reviewed_at', 'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'migrated_from_sheets' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreEnrollmentPayment::class);
    }

    public function latestCharge(): ?PreEnrollmentPayment
    {
        return $this->payments()->where('type', 'charge')->latest()->first();
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('type', 'charge')->exists();
    }

    public function isRefunded(): bool
    {
        return $this->payments()->where('type', 'refund')->exists();
    }

    public function getFullNameAttribute(): string
    {
        $lname = $this->payload['lname'] ?? '';
        $fname = $this->payload['fname'] ?? '';

        return trim("{$lname}, {$fname}", ', ');
    }
}
