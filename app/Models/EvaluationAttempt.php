<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'online_evaluation_id',
        'user_id',
        'attempt_number',
        'started_at',
        'submitted_at',
        'status',
        'score',
        'answers',
        'block_scores',
        'content_snapshot',
        'rubric_snapshot',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'answers' => 'array',
            'block_scores' => 'array',
            'content_snapshot' => 'array',
            'rubric_snapshot' => 'array',
        ];
    }

    public function gradingSuggestions(): HasMany
    {
        return $this->hasMany(GradingSuggestion::class, 'evaluation_attempt_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(OnlineEvaluation::class, 'online_evaluation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AttemptStatus::Submitted->value,
            AttemptStatus::Graded->value,
        ]);
    }

    public function getStatusAttribute(): AttemptStatus
    {
        return AttemptStatus::tryFrom($this->attributes['status'] ?? '') ?? AttemptStatus::InProgress;
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status->badgeClass();
    }
}
