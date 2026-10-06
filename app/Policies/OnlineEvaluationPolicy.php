<?php

namespace App\Policies;

use App\Models\OnlineEvaluation;
use App\Models\User;

/**
 * Access rules for online evaluations.
 *
 * Management (create/update/delete/grade) is limited to staff who either have
 * global reach or actually teach the evaluated subject. Taking an evaluation is
 * limited to enrolled students, and only while the evaluation is open.
 */
class OnlineEvaluationPolicy
{
    /**
     * Roles allowed to manage every subject's evaluations, not just their own.
     */
    private const GLOBAL_REACH = ['admin', 'principal', 'director', 'administrative'];

    /**
     * Roles allowed to reach the management screens at all.
     */
    private const MANAGERS = ['admin', 'teacher', 'principal', 'director', 'administrative'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }

    public function view(User $user, OnlineEvaluation $evaluation): bool
    {
        if ($user->hasAnyRole(self::MANAGERS)) {
            return true;
        }

        return $user->hasRole('student') && $evaluation->enrollsUser($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }

    public function update(User $user, OnlineEvaluation $evaluation): bool
    {
        return $this->manage($user, $evaluation);
    }

    public function delete(User $user, OnlineEvaluation $evaluation): bool
    {
        return $this->manage($user, $evaluation);
    }

    public function grade(User $user, OnlineEvaluation $evaluation): bool
    {
        return $this->manage($user, $evaluation);
    }

    /**
     * Sync final grades into the subject gradebook.
     */
    public function syncGrades(User $user, OnlineEvaluation $evaluation): bool
    {
        return $this->manage($user, $evaluation);
    }

    /**
     * Take an attempt. Also covers the teacher preview mode, which only renders
     * the questions and never writes an attempt.
     */
    public function take(User $user, OnlineEvaluation $evaluation): bool
    {
        if ($user->hasAnyRole(self::MANAGERS)) {
            return true;
        }

        return $evaluation->isAvailableFor($user);
    }

    /**
     * See the grading breakdown, which teachers reach through `take` in preview.
     */
    public function preview(User $user, OnlineEvaluation $evaluation): bool
    {
        return $this->manage($user, $evaluation);
    }

    /**
     * See results: teachers manage, students see only their own attempts.
     */
    public function results(User $user, OnlineEvaluation $evaluation): bool
    {
        return $user->hasAnyRole(self::MANAGERS)
            || ($user->hasRole('student') && $evaluation->enrollsUser($user));
    }

    private function manage(User $user, OnlineEvaluation $evaluation): bool
    {
        if ($user->hasAnyRole(self::GLOBAL_REACH)) {
            return true;
        }

        return $user->hasRole('teacher') && $evaluation->isManageableBy($user);
    }
}
