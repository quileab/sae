<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleGroups;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'lastname',
        'firstname',
        'email',
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

    public static function getRoleName(string $name): string
    {
        return UserRole::tryFrom($name)?->label() ?? 'error';
    }

    public static function roleOptions(): array
    {
        return UserRole::options();
    }

    // users may have multiple careers
    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'enrollments', 'user_id', 'subject_id')
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

    public function userPayments(): HasMany
    {
        return $this->hasMany(UserPayment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany('App\Models\PaymentRecord');
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array|string $roles): bool
    {
        if (is_string($roles)) {
            return $this->hasRole($roles);
        }

        return in_array($this->role, $roles);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(RoleGroups::values(RoleGroups::STAFF));
    }

    // full name attribute
    public function getFullNameAttribute(): string
    {
        return $this->lastname.', '.$this->firstname;
    }

    public function getAvatarUrlAttribute(): string
    {
        $path = 'avatars/'.$this->id.'.webp';

        if (Storage::disk('public')->exists($path)) {
            $timestamp = Storage::disk('public')->lastModified($path);

            return asset('storage/'.$path).'?v='.$timestamp;
        }

        $fullNameForAvatar = trim($this->firstname.' '.$this->lastname);

        return 'https://ui-avatars.com/api/?name='.urlencode($fullNameForAvatar).'&background=random&color=fff';
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

    public function justifiedAbsences(): HasMany
    {
        return $this->hasMany(JustifiedAbsence::class);
    }
}
