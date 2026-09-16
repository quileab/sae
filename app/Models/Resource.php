<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'title',
        'url',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Resolve the Heroicon name based on the resource URL domain.
     */
    public function getIconAttribute(): string
    {
        return match (true) {
            str_contains($this->url, 'drive.google.com') => 'o-cloud',
            str_contains($this->url, 'youtube.com') => 'o-play-circle',
            str_contains($this->url, 'meet.google.com') => 'o-video-camera',
            str_contains($this->url, 'docs.google.com') => 'o-document-text',
            default => 'o-link',
        };
    }
}
