<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookLoan extends Model
{
    /** @use HasFactory<\Database\Factories\BookLoanFactory> */
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'loan_date',
        'return_date',
        'returned_at',
        'notes',
        'status',
    ];

    protected $casts = [
        'loan_date' => 'date',
        'return_date' => 'date',
        'returned_at' => 'datetime',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
