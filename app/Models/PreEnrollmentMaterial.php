<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreEnrollmentMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['cycle_id', 'career_id', 'title', 'file_path', 'original_name'];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * Materiales del ciclo de la preinscripción, generales o de su carrera.
     *
     * @return Collection<int, PreEnrollmentMaterial>
     */
    public static function forPreEnrollment(PreEnrollment $preEnrollment): Collection
    {
        return static::query()
            ->where('cycle_id', $preEnrollment->cycle_id)
            ->where(fn ($query) => $query
                ->whereNull('career_id')
                ->orWhere('career_id', $preEnrollment->career_id))
            ->orderBy('title')
            ->get();
    }
}
