<?php

namespace App\Models;

use App\Enums\GradingFlag;
use App\Enums\GradingSuggestionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An assistant grader's proposal for one block of one attempt.
 *
 * This is deliberately a separate table from block_scores: the proposal is never
 * a mark. `block_scores` and `score` stay owned by the teacher, who accepts a
 * suggestion through AttemptGrader so the /10 normalisation keeps a single
 * source of truth.
 */
class GradingSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_attempt_id',
        'block_id',
        'rubric_version',
        'rubric_hash',
        'agent',
        'criteria',
        'total_points',
        'max_points',
        'flags',
        'confidence',
        'state',
        'revision',
        'history',
        'disagreement_reason',
        'final_points',
        'raw_response',
        'accepted_at',
        'accepted_by',
    ];

    protected function casts(): array
    {
        return [
            'rubric_version' => 'integer',
            'criteria' => 'array',
            'total_points' => 'decimal:2',
            'max_points' => 'decimal:2',
            'flags' => 'array',
            'confidence' => 'decimal:2',
            'revision' => 'integer',
            'history' => 'array',
            'final_points' => 'decimal:2',
            'accepted_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EvaluationAttempt::class, 'evaluation_attempt_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('state', GradingSuggestionState::Proposed->value);
    }

    public function getStateAttribute(): GradingSuggestionState
    {
        return GradingSuggestionState::tryFrom($this->attributes['state'] ?? '') ?? GradingSuggestionState::Proposed;
    }

    public function getStateLabelAttribute(): string
    {
        return $this->state->label();
    }

    public function getStateBadgeAttribute(): string
    {
        return $this->state->badgeClass();
    }

    /**
     * @return array<int, GradingFlag>
     */
    public function flagList(): array
    {
        return collect($this->flags ?? [])
            ->map(fn (string|int $flag) => GradingFlag::tryFrom((string) $flag))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Suggestions the assistant flagged as suspicious lead the review queue.
     */
    public function requiresAttention(): bool
    {
        return collect($this->flagList())->contains(fn (GradingFlag $flag) => $flag->requiresAttention());
    }

    public function isPending(): bool
    {
        return $this->state->isPending();
    }
}
