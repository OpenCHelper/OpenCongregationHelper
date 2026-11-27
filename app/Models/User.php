<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Observers\UserObserver;
use App\Settings\SecuritySettings;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Spatie\Permission\Traits\HasRoles;
use STS\FilamentImpersonate\Facades\Impersonation;
use Throwable;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasLocalePreference, HasName, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasPushSubscriptions;
    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use LogsActivity;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The deletion guard is registered before the "deleting" listeners of the traits (registered by `boot()`), which
     * would otherwise act on a deletion it then refuses, e.g. `HasRoles` detaching the roles.
     */
    protected static function booting(): void
    {
        static::deleting(fn (User $user): ?bool => app(UserObserver::class)->guardDeletion($user));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['first_name', 'last_name', 'email']);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /**
     * The guard of the roles and permissions (Spatie), whatever guard authenticated the user: the AI assistants use the
     * `api` guard (OAuth tokens of the MCP server), but the roles and permissions are all defined for `web`.
     */
    protected string $guard_name = 'web';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'can_login',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The default values of the attributes, so that the two-factor authentication can be read from a user just created
     * (the missing attributes are not lazily loaded).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => null,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'can_login' => 'boolean',
        ];
    }

    public function getFullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * Get the users that can be selected as assignees, sorted by name.
     *
     * @return array<int, string>
     */
    public static function getAssigneeOptions(): array
    {
        return static::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->pluck('full_name', 'id')
            ->all();
    }

    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getFullName(),
        );
    }

    /**
     * Fallback for Filament to allow easier migration from `name` to `fullName`.
     *
     * @deprecated Use `fullName` or `getFullName()` instead.
     */
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getFullName(),
        );
    }

    public function getFilamentName(): string
    {
        return $this->getFullName();
    }

    /**
     * The photo uploaded from the profile page, or `null` to let Filament generate the avatar from the initials.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        // The URL is an accessor of the `HasProfilePhoto` trait of Jetstream
        return $this->profile_photo_path ? $this->getAttribute('profile_photo_url') : null;
    }

    /**
     * The ID of the root user: the first user (ID 1), until the root user hands its role over to another super
     * administrator ({@see SecuritySettings::$root_user_id}).
     *
     * Before the setting exists (e.g. while the installation creates the tables, or before its migration runs) it is the
     * first user. Any other error is raised: falling back to the first user would move the protections of the root user
     * to the wrong user after a handover.
     */
    public static function rootUserId(): int
    {
        try {
            return app(SecuritySettings::class)->root_user_id;
        } catch (MissingSettings) {
            return 1;
        } catch (QueryException $exception) {
            if (! Schema::hasTable(config('settings.repositories.database.table') ?? 'settings')) {
                return 1;
            }

            throw $exception;
        }
    }

    /**
     * Check if this user is the root user.
     */
    public function isRootUser(): bool
    {
        return $this->exists && $this->getKey() === static::rootUserId();
    }

    /**
     * Get the root user, if it exists.
     */
    public static function rootUser(): ?self
    {
        return static::query()->find(static::rootUserId());
    }

    /**
     * Make this user the root user, in place of the current one. Only a super administrator who can log in can be
     * the root user, so that there is always someone to manage the application.
     */
    public function makeRootUser(): void
    {
        if (! $this->exists || ! $this->canActuallyLogIn() || ! $this->hasRole('super_admin')) {
            throw new LogicException('Only a super administrator who can log in can become the root user.');
        }

        $settings = app(SecuritySettings::class);
        $settings->root_user_id = $this->getKey();
        $settings->save();
    }

    /**
     * Whether this user can log in now: their access is enabled and they have chosen their password (a user still
     * waiting for their invitation has none). Based on the saved values.
     */
    public function canActuallyLogIn(): bool
    {
        return $this->exists && $this->getOriginal('can_login') && filled($this->getOriginal('password'));
    }

    /**
     * Whether this user is a super administrator who can log in, and the only one: removing their role or their
     * access, or deleting them, would leave nobody able to manage the application.
     */
    public function isLastSuperAdmin(): bool
    {
        return $this->canActuallyLogIn()
            && $this->hasRole('super_admin')
            && static::role('super_admin')
                ->where('can_login', true)
                ->whereNotNull('password')
                ->where('password', '!=', '')
                ->whereKeyNot($this->getKey())
                ->doesntExist();
    }

    /**
     * Create the root user, with ID 1 whatever the next ID of the table (e.g. after a failed attempt or deleted users).
     *
     * An explicit ID needs `IDENTITY_INSERT` on SQL Server, and does not move the sequence of PostgreSQL on: the next
     * user would get ID 1 again.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createRoot(array $attributes): self
    {
        $model = new self;
        $connection = $model->getConnection();
        $table = $connection->getQueryGrammar()->wrapTable($model->getTable());
        $driver = $connection->getDriverName();

        if ($driver === 'sqlsrv') {
            $connection->unprepared("SET IDENTITY_INSERT {$table} ON");
        }

        try {
            $root = static::forceCreate(['id' => 1, ...$attributes]);
        } finally {
            if ($driver === 'sqlsrv') {
                $connection->unprepared("SET IDENTITY_INSERT {$table} OFF");
            }
        }

        if ($driver === 'pgsql') {
            $connection->select("select setval(pg_get_serial_sequence(?, 'id'), (select max(id) from {$table}))", [
                $connection->getTablePrefix().$model->getTable(),
            ]);
        }

        // The root user may have been handed over to another user before the users were wiped
        try {
            $settings = app(SecuritySettings::class);

            if ($settings->root_user_id !== $root->getKey()) {
                $settings->root_user_id = $root->getKey();
                $settings->save();
            }
        } catch (Throwable) {
            // The settings don't exist yet: the root user is the first user
        }

        return $root;
    }

    /**
     * Check if the user has set up two-factor authentication (with an authenticator app).
     */
    public function hasTwoFactorAuthentication(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    /**
     * Check if the root user's login information should be protected.
     * Only protects in non-production environments.
     */
    public function isRootUserProtected(): bool
    {
        return $this->isRootUser() && ! app()->environment('production');
    }

    /**
     * Check if this user can be deleted.
     * Root user can NEVER be deleted in any environment.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->isRootUser();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Virtual users cannot log in, but administrators can still impersonate them.
        return (bool) $this->can_login || Impersonation::isImpersonating();
    }

    /**
     * Only super administrators can impersonate other users.
     */
    public function canImpersonate(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Every user (virtual ones included) can be impersonated, except the root user.
     */
    public function canBeImpersonated(): bool
    {
        return ! $this->isRootUser();
    }

    public function isVirtual(): bool
    {
        return ! $this->can_login;
    }

    /**
     * Whether the user can log in but has not chosen their password yet, from the invitation sent when they were given
     * access (see `UserInvitationNotification`).
     */
    public function isAwaitingInvitation(): bool
    {
        return $this->can_login && blank($this->password) && ! $this->isRootUser();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TerritoryAssignment::class, 'assignee_id');
    }

    /**
     * Whether any territory has ever been assigned to the user, deleted assignments included: such a user cannot be
     * deleted, or the history of the territories would be lost with them (see `UserObserver::guardDeletion()`).
     */
    public function hasAssignments(): bool
    {
        return $this->assignments()->withTrashed()->exists();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(Contact::class)->where('type', ContactType::EMERGENCY);
    }

    /**
     * Get the departments the user serves in.
     *
     * @return BelongsToMany<Department, $this, DepartmentMember>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->using(DepartmentMember::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include the users serving in the given department (with the given role, when given).
     *
     * @param  Builder<self>  $query
     */
    public function scopeInDepartment(Builder $query, DepartmentKey $department, ?DepartmentRole $role = null): void
    {
        $query->whereHas('departments', function (Builder $query) use ($department, $role): void {
            $query->where('key', $department->value)
                ->when($role, fn (Builder $query) => $query->where('department_user.role', $role->value));
        });
    }

    /**
     * Check if the user serves in the given department.
     */
    public function isMemberOf(DepartmentKey $department): bool
    {
        return $this->getDepartmentKeys()->contains($department);
    }

    /**
     * Check if the user is the overseer of a department (any one, when not given).
     */
    public function isDepartmentOverseer(?DepartmentKey $department = null): bool
    {
        if (! $this->exists) {
            return false;
        }

        return $this->loadMissing('departments')->departments->contains(
            fn (Department $joined): bool => $joined->pivot->role === DepartmentRole::Overseer
                && ($department === null || $joined->key === $department),
        );
    }

    /**
     * The actions on the sections of a department that only its overseers are granted: the assistants can do
     * everything else, but not delete (e.g. deleting a publication deletes its history too).
     *
     * @var list<string>
     */
    public const OVERSEER_ONLY_ACTIONS = ['Delete', 'DeleteAny', 'ForceDelete', 'ForceDeleteAny', 'Restore', 'RestoreAny'];

    /**
     * Check if the departments of the user grant the given permission (`Action:Subject`, as checked by the
     * policies): the members of a department can do everything on its sections, except deleting for the assistants
     * (see `OVERSEER_ONLY_ACTIONS`). The permissions of their roles still apply on top.
     */
    public function hasDepartmentPermission(string $permission): bool
    {
        if (! str_contains($permission, ':') || ! $this->exists) {
            return false;
        }

        [$action, $subject] = explode(':', $permission, 2);
        $overseersOnly = in_array($action, self::OVERSEER_ONLY_ACTIONS, true);

        return $this->loadMissing('departments')->departments->contains(
            static fn (Department $department): bool => in_array($subject, $department->key->getPermissionSubjects(), true)
                && (! $overseersOnly || $department->pivot->role === DepartmentRole::Overseer),
        );
    }

    /**
     * Get the keys of the departments the user serves in, loaded once per instance.
     *
     * @return Collection<int, DepartmentKey>
     */
    public function getDepartmentKeys(): Collection
    {
        if (! $this->exists) {
            return collect();
        }

        return $this->loadMissing('departments')->departments->pluck('key');
    }

    /**
     * Get the publications the user receives on paper regularly.
     *
     * @return HasOne<PublicationSubscriber, $this>
     */
    public function publicationSubscriber(): HasOne
    {
        return $this->hasOne(PublicationSubscriber::class);
    }

    /**
     * Get the publications the user requested.
     *
     * @return HasMany<PublicationRequest, $this>
     */
    public function publicationRequests(): HasMany
    {
        return $this->hasMany(PublicationRequest::class);
    }

    /**
     * Get the user's preferred locale.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?? app()->getLocale();
    }
}
