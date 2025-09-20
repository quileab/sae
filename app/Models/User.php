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
    // protected $fillable = [
    //     'id',
    //     'role',
    //     'name',
    //     'email',
    //     'password',
    // ];
    protected $guarded = [];

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
        ['id' => 'admin', 'name' => 'ADMIN'],
        ['id' => 'student', 'name' => 'Estudiante'],
        ['id' => 'teacher', 'name' => 'Profesor'],
        ['id' => 'director', 'name' => 'Director'],
        ['id' => 'administrative', 'name' => 'Administrativo'],
        ['id' => 'treasurer', 'name' => 'Tesorero'],
        ['id' => 'user', 'name' => 'Usuario']
    ];

    //public static function that reurns role name from id
    public static function getRoleName(string $id): string
    {
        // return "name" from asociative array $roles[id=>'', name=>''], use id to return name
        return array_column(self::$roles, 'name', 'id')[$id] ?? 'error';
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
