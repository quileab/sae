<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubjectOptions
{
    public const MAX_RESULTS = 50;

    /**
     * Roles that can see every subject in the catalogue.
     *
     * @var list<string>
     */
    public const GLOBAL_ROLES = ['admin', 'principal', 'director', 'administrative'];

    public function canSeeAll(User $user): bool
    {
        return $user->hasAnyRole(self::GLOBAL_ROLES);
    }

    /**
     * Base query with the subjects the user is allowed to see.
     */
    public function queryFor(User $user): Builder
    {
        $query = Subject::query();

        if (! $this->canSeeAll($user)) {
            $query->whereIn('id', $user->subjects()->pluck('subjects.id')->all());
        }

        return $query;
    }

    /**
     * Search subjects and return MaryUI `x-choices` ready options.
     *
     * @param  array<int, int|string|null>  $includeIds
     * @return array<int, array{id: int, name: string}>
     */
    public function search(User $user, string $term = '', ?int $careerId = null, array $includeIds = []): array
    {
        $query = $this->queryFor($user);

        if ($careerId) {
            $query->where('career_id', $careerId);
        }

        return $this->searchWith($query, $term, $includeIds);
    }

    /**
     * @param  array<int, int|string|null>  $includeIds
     * @return array<int, array{id: int, name: string}>
     */
    public function searchWith(Builder $query, string $term = '', array $includeIds = []): array
    {
        $selectedIds = array_values(array_filter(array_map('intval', $includeIds)));

        // Keep only the selected subjects the current query is allowed to see.
        if ($selectedIds !== []) {
            $selectedIds = (clone $query)->whereKey($selectedIds)->pluck('id')->all();
        }

        $term = trim($term);

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('id', 'like', "%{$term}%");
            });
        }

        $subjects = $query->orderBy('name')->limit(self::MAX_RESULTS)->get();

        if ($selectedIds !== []) {
            $subjects = $subjects
                ->merge(Subject::whereKey($selectedIds)->get())
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        return $this->map($subjects);
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, array{id: int, name: string}>
     */
    public function map(Collection $subjects): array
    {
        return $subjects
            ->map(fn (Subject $subject) => [
                'id' => (int) $subject->id,
                'name' => $subject->id.' - '.$subject->name,
            ])
            ->values()
            ->all();
    }
}
