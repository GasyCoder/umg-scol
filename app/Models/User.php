<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property int|null $student_id
 * @property int|null $establishment_id
 * @property string $uuid
 * @property bool $is_active
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'normalized_email', 'student_id'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    use HasRoles {
        HasRoles::hasPermissionTo as protected spatieHasPermissionTo;
        HasRoles::getAllPermissions as protected spatieGetAllPermissions;
    }

    protected string $guard_name = 'web';

    /** A new account is active until an administrator suspends it. */
    protected $attributes = ['is_active' => true];

    /**
     * Role permissions withdrawn from this account only (additions are Spatie direct permissions).
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function deniedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_denied_permissions');
    }

    /**
     * Effective rights = role permissions + direct additions - withdrawals. Every check goes
     * through here (Gate, can(), Policies, middleware), so a withdrawal applies everywhere.
     *
     * @param  string|int|Permission|\BackedEnum  $permission
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if (! $this->spatieHasPermissionTo($permission, $guardName)) {
            return false;
        }
        $permission = $this->filterPermission($permission, $guardName);

        return ! $this->loadMissing('deniedPermissions')->deniedPermissions->contains('id', $permission->id);
    }

    /** @return Collection<int, Permission> */
    public function getAllPermissions(): Collection
    {
        $denied = $this->loadMissing('deniedPermissions')->deniedPermissions->pluck('id')->all();

        return $this->spatieGetAllPermissions()->reject(fn (Permission $permission) => in_array($permission->id, $denied, true))->values();
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('identity')
            ->logOnly(['name', 'email_verified_at', 'establishment_id', 'is_active'])
            ->logOnlyDirty()
            ->logEmptyChanges();
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function hasUsableEstablishment(): bool
    {
        return ! $this->hasRole('establishment') || (
            $this->establishment_id !== null
            && Establishment::query()->whereKey($this->establishment_id)->where('is_active', true)->exists()
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'establishment_id' => 'integer',
            'student_id' => 'integer',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<StudentRecord, $this> */
    public function declaredStudentRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class, 'declared_by');
    }
}
