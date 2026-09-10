<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Services\DefaultTenantRoles;
use App\Services\TenantRbac;
use App\Support\PermissionCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('is_active') && ! $user->is_active) {
                app(TenantRbac::class)->assertCanDeactivateUser($user);
            }
        });
        static::deleting(fn (self $user) => app(TenantRbac::class)->assertCanDeactivateUser($user));
        static::created(function (self $user): void {
            $systemKey = match ($user->role) {
                UserRole::ADMIN => DefaultTenantRoles::TENANT_ADMINISTRATOR,
                UserRole::TEACHER => 'teacher',
                default => null,
            };
            if ($systemKey && $user->tenant_id && Schema::hasTable('roles')) {
                $context = app(TenantContext::class);
                $previousTenantId = $context->id();
                $context->set((int) $user->tenant_id);
                try {
                    $role = Role::where('system_key', $systemKey)->where('is_active', true)->first();
                    if ($role) {
                        app(TenantRbac::class)->assignRoles($user, [$role->id]);
                    }
                } finally {
                    $previousTenantId ? $context->set($previousTenantId) : $context->clear();
                }
            }
        });
    }

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

    public function roles(): BelongsToMany
    {
        $relation = $this->belongsToMany(Role::class, 'role_user')->withPivot('tenant_id', 'data_scope')->withTimestamps();
        $tenantId = app(TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function hasPermission(string $key): bool
    {
        if ($this->role === UserRole::SUPER_ADMIN) {
            return false;
        }
        $key = PermissionCatalog::normalize($key);
        $roles = $this->relationLoaded('roles')
            ? $this->roles->where('is_active', true)
            : $this->roles()->where('roles.is_active', true)->with('permissions:id,key')->get();
        if ($roles->isEmpty() && ! Role::query()->exists()) {
            return match ($this->role) {
                UserRole::ADMIN => true,
                UserRole::TEACHER => in_array($key, DefaultTenantRoles::templates()['teacher']['permissions'], true),
                default => false,
            };
        }

        return $roles->contains(fn (Role $role) => $role->permissions->contains('key', $key));
    }

    public function hasSystemRole(string $systemKey): bool
    {
        return $this->roles()->where('roles.is_active', true)->where('roles.system_key', $systemKey)->exists();
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
        return $this->hasMany(SchoolGroup::class, 'principal_teacher_id');
    }

    public function schoolGroups(): BelongsToMany
    {
        $relation = $this->belongsToMany(SchoolGroup::class, 'school_group_teacher', 'teacher_id', 'school_group_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function schoolSites(): BelongsToMany
    {
        $relation = $this->belongsToMany(SchoolSite::class, 'school_site_user')->withPivot('tenant_id');
        $tenantId = app(TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
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
