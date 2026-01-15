<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Enrollment;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'role',
        'name',
        'lastname',
        'firstname',
        'email',
        'password',
        'enabled',
        'phone',
    ];
    // protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'enabled' => 'boolean',
        ];
    }

    public static $roles = [
        ['id' => 1, 'name' => 'admin', 'alias' => 'ADMIN'],
        ['id' => 2, 'name' => 'student', 'alias' => 'Estudiante'],
        ['id' => 3, 'name' => 'teacher', 'alias' => 'Profesor'],
        ['id' => 4, 'name' => 'director', 'alias' => 'Director'],
        ['id' => 5, 'name' => 'administrative', 'alias' => 'Administrativo'],
        ['id' => 6, 'name' => 'treasurer', 'alias' => 'Tesorero'],
        ['id' => 7, 'name' => 'user', 'alias' => 'Usuario']
    ];

    //public static function that reurns role name from id
    public static function getRoleName(string $name): string
    {
        $role = collect(self::$roles)->firstWhere('name', $name);
        return $role['alias'] ?? 'error';
    }
    // users may have multiple careers
    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class);
    }

    public function book(): HasMany
    {
        return $this->hasMany('App\Models\Books');
    }

    public function subjects()
    {
        return $this->belongsToMany('App\Models\Subject', 'enrollments', 'user_id', 'subject_id')
            ->orderBy('id', 'asc');
    }

    public function hasSubject($subject_id): bool
    {
        return Enrollment::where('user_id', $this->id)
            ->where('subject_id', $subject_id)
            ->exists();
    }

    // user has many grades
    public function grades(): HasMany
    {
        return $this->hasMany('App\Models\Grade');
    }
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany('App\Models\PaymentRecord');
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function hasNotRole($role)
    {
        return $this->role !== $role;
    }

    public function hasAnyRole($roles)
    {
        return in_array($this->role, $roles);
    }

    // full name attribute
    public function getFullNameAttribute(): string
    {
        return $this->lastname . ', ' . $this->firstname;
    }

    public function classSessions()
    {
        return $this->hasMany(ClassSession::class, 'teacher_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'message_user', 'user_id', 'message_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }
}
