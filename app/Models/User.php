<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'birth_date',
        'job_title',
        'can_login',
        'tenant_id',
        'temporary_password_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'birth_date' => 'date:Y-m-d',
            'can_login' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
        ];
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Expense::class, 'employee_id')->where('category', 'Salaire');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function schoolParent(): HasOne
    {
        return $this->hasOne(SchoolParent::class);
    }

    public function schoolParents(): HasMany
    {
        return $this->hasMany(SchoolParent::class);
    }

    public function mobileMemberships(): HasMany
    {
        return $this->hasMany(MobileMembership::class);
    }

    public function portalNotifications(): HasMany
    {
        return $this->hasMany(PortalNotification::class, 'recipient_id')->latest('occurred_at');
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function taughtSubjects(): BelongsToMany
    {
        $relation = $this->belongsToMany(Course::class, 'course_teacher', 'teacher_id', 'course_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function taughtGroups(): BelongsToMany
    {
        $relation = $this->belongsToMany(TrainingPlanGroup::class, 'group_teacher', 'teacher_id', 'training_plan_group_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function principalGroups(): HasMany
    {
        return $this->hasMany(TrainingPlanGroup::class, 'principal_teacher_id');
    }

    public function timetableSessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class, 'teacher_id');
    }

    public function timetableAvailabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class, 'teacher_id');
    }

    public function timetableUnavailablePeriods(): HasMany
    {
        return $this->hasMany(TeacherUnavailablePeriod::class, 'teacher_id');
    }

    /**
     * Get the reservations for the user.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /**
     * Get the payments for the user.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
