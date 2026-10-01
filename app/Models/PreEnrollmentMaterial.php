<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreEnrollmentMaterial extends Model
{
    protected $fillable = ['cycle_id', 'career_id', 'title', 'file_path', 'original_name'];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
