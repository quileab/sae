<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleGroups;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'birth_date',
        'status',
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
            'birth_date' => 'date',
            'enabled' => 'boolean',
            'status' => UserStatus::class,
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

    public static function statusOptions(): array
    {
        return UserStatus::options();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status instanceof UserStatus ? $this->status->label() : ($this->status ?? 'Activo');
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status instanceof UserStatus ? $this->status->badgeClass() : 'badge-neutral';
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
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

    public function isAdministrative(): bool
    {
        return $this->hasAnyRole(RoleGroups::values(RoleGroups::ADMINISTRATIVE));
    }

    public function isTutor(): bool
    {
        return $this->role === UserRole::Tutor->value;
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_tutor', 'tutor_id', 'student_id')
            ->withPivot(['relationship', 'is_emergency_contact', 'is_authorized_pickup', 'notes'])
            ->withTimestamps();
    }

    public function tutors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_tutor', 'student_id', 'tutor_id')
            ->withPivot(['relationship', 'is_emergency_contact', 'is_authorized_pickup', 'notes'])
            ->withTimestamps();
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function isAdult(): bool
    {
        return $this->birth_date ? $this->birth_date->age >= 18 : false;
    }

    public function isTertiaryStudent(): bool
    {
        if (! method_exists($this, 'careers')) {
            return false;
        }

        return $this->careers()->where('level', 'tertiary')->exists();
    }

    public function canManageOwnMedicalRecord(): bool
    {
        if ($this->isStaff()) {
            return true;
        }

        if ($this->isAdult()) {
            return true;
        }

        if ($this->isTertiaryStudent()) {
            return true;
        }

        // If user has no specific secondary-only career assigned, default to tertiary institute autonomy
        $hasOnlySecondary = method_exists($this, 'careers')
            && $this->careers()->where('level', 'secondary')->exists()
            && ! $this->careers()->where('level', 'tertiary')->exists();

        return ! $hasOnlySecondary;
    }

    public function authoredAnnouncements(): HasMany
    {
        return $this->hasMany(Event::class, 'user_id')->where('type', 'announcement');
    }

    public function receivedAnnouncements(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user', 'user_id', 'event_id')
            ->where('events.type', 'announcement')
            ->withPivot(['read_at', 'acknowledged_at'])
            ->withTimestamps();
    }

    // full name attribute
    public function getFullNameAttribute(): string
    {
        return $this->lastname.', '.$this->firstname;
    }

    public function getAvatarUrlAttribute(): string
    {
        $path = 'avatars/'.$this->id.'.webp';
        $fullPublicPath = public_path('storage/'.$path);

        if (file_exists($fullPublicPath)) {
            $timestamp = filemtime($fullPublicPath);

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

    public function evaluationAttempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class);
    }

    public function authoredEvaluations(): HasMany
    {
        return $this->hasMany(OnlineEvaluation::class, 'teacher_id');
    }
}
