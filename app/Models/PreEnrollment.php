<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreEnrollment extends Model
{
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
}
