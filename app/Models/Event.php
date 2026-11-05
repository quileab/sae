<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Event extends Model
{
    protected $fillable = [
        'type',
        'title',
        'description',
        'start',
        'end',
        'color',
        'priority',
        'target',
        'target_roles',
        'user_id',
        'career_id',
        'subject_id',
        'requires_acknowledgment',
        'show_in_calendar',
        'published_at',
        'expires_at',
        'presidente_id',
        'vocal1_id',
        'vocal2_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'target_roles' => 'array',
            'requires_acknowledgment' => 'boolean',
            'show_in_calendar' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function presidente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presidente_id');
    }

    public function vocal1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vocal1_id');
    }

    public function vocal2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vocal2_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot(['read_at', 'acknowledged_at'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeAnnouncements(Builder $query): Builder
    {
        return $query->where('type', 'announcement');
    }

    public function scopeCalendarEvents(Builder $query): Builder
    {
        return $query->where('show_in_calendar', true);
    }

    public function scopeExamBoards(Builder $query): Builder
    {
        return $query->where('type', 'exam_board');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin') || (method_exists($user, 'isStaff') && $user->isStaff())) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $userRoleString = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

            $q->where('target', 'all')
                ->orWhere(function (Builder $sub) use ($userRoleString) {
                    $sub->whereNotNull('target_roles')
                        ->whereJsonContains('target_roles', $userRoleString);
                });

            // Target by Career
            if (method_exists($user, 'careers')) {
                $careerIds = $user->careers()->pluck('careers.id')->toArray();
                if (! empty($careerIds)) {
                    $q->orWhereIn('career_id', $careerIds);
                }
            }

            // Target by Subject
            if (method_exists($user, 'subjects')) {
                $subjectIds = $user->subjects()->pluck('subjects.id')->toArray();
                if (! empty($subjectIds)) {
                    $q->orWhereIn('subject_id', $subjectIds);
                }
            }

            // If user is a tutor, match their students' subjects and careers
            if ($user->hasRole('tutor') && method_exists($user, 'students')) {
                $studentIds = $user->students()->pluck('users.id')->toArray();
                if (! empty($studentIds)) {
                    $studentSubjectIds = DB::table('enrollments')
                        ->whereIn('user_id', $studentIds)
                        ->where('status', 'active')
                        ->pluck('subject_id')
                        ->toArray();

                    if (! empty($studentSubjectIds)) {
                        $q->orWhereIn('subject_id', $studentSubjectIds);
                    }
                }
            }
        });
    }

    // Helpers
    public function markAsReadFor(User $user): void
    {
        $this->readers()->syncWithPivotValues(
            [$user->id],
            ['read_at' => now()],
            false
        );
    }

    public function acknowledgeFor(User $user): void
    {
        $this->readers()->syncWithPivotValues(
            [$user->id],
            [
                'read_at' => now(),
                'acknowledged_at' => now(),
            ],
            false
        );
    }

    public function isReadBy(User $user): bool
    {
        return $this->readers()
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->exists();
    }

    public function isAcknowledgedBy(User $user): bool
    {
        return $this->readers()
            ->where('user_id', $user->id)
            ->whereNotNull('acknowledged_at')
            ->exists();
    }
}
