<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'publisher',
        'author',
        'gender',
        'extent',
        'edition',
        'isbn',
        'container',
        'signature',
        'digital',
        'origin',
        'date_added',
        'price',
        'discharge_date',
        'discharge_reason',
        'synopsis',
        'note',
        'user_id',
    ];

    protected $casts = [
        'edition' => 'date',
        'date_added' => 'date',
        'discharge_date' => 'date',
        'price' => 'decimal:2',
        'extent' => 'integer',
        'user_id' => 'integer',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }
}
