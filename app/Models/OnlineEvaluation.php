<?php

namespace App\Models;

use App\Enums\EvaluationGradingMethod;
use App\Enums\EvaluationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'class_session_id',
        'teacher_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'status',
        'passing_score',
        'max_attempts',
        'grading_method',
        'shuffle_questions',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'passing_score' => 'decimal:2',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'content' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EvaluationStatus::Published->value);
    }

    /**
     * Evaluations the given user is entitled to manage.
     *
     * Staff with global reach manage everything; everyone else is limited to
     * the subjects they actually teach.
     */
    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'principal', 'director'])) {
            return $query;
        }

        return $query->whereIn('subject_id', Enrollment::query()
            ->where('user_id', $user->id)
            ->select('subject_id'));
    }

    /**
     * Whether a student may open or resume an attempt right now.
     *
     * Only answers "is it open to them": lifecycle status, availability window
     * and enrolment. Ownership of the evaluation is authorised separately by
     * OnlineEvaluationPolicy.
     */
    public function isAvailableFor(User $user): bool
    {
        if ($this->status !== EvaluationStatus::Published->value) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }

        return $this->enrollsUser($user);
    }

    /**
     * Whether the user is inscribed in the evaluated subject, either directly or
     * through the linked class session.
     */
    public function enrollsUser(User $user): bool
    {
        if ($user->hasSubject($this->subject_id)) {
            return true;
        }

        if (! $this->class_session_id) {
            return false;
        }

        return $this->classSession?->students()->whereKey($user->id)->exists() ?? false;
    }

    /**
     * Whether the user may create, edit, delete or grade this evaluation.
     */
    public function isManageableBy(User $user): bool
    {
        if ($user->hasAnyRole(['admin', 'principal', 'director'])) {
            return true;
        }

        return $user->hasSubject($this->subject_id);
    }

    public function getStatusLabelAttribute(): string
    {
        return EvaluationStatus::tryFrom($this->status)?->label() ?? ucfirst((string) $this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return EvaluationStatus::tryFrom($this->status)?->badgeClass() ?? 'badge-neutral';
    }

    public function getGradingMethodLabelAttribute(): string
    {
        return EvaluationGradingMethod::tryFrom($this->grading_method)?->label() ?? '—';
    }

    public function getGradingMethodShortLabelAttribute(): string
    {
        return EvaluationGradingMethod::tryFrom($this->grading_method)?->shortLabel() ?? '—';
    }

    public function getPassingScoreAttribute(): float
    {
        return (float) ($this->attributes['passing_score'] ?? 6);
    }
}
