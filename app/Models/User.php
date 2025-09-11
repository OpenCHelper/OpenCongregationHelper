<?php

namespace App\Models;

use App\Enums\ContactType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\HasName;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\UserObserver;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements HasTenants, HasName, MustVerifyEmail, FilamentUser , HasLocalePreference
{
    use HasApiTokens;

    use HasRoles;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use LogsActivity;
    
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
        ];
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
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
     * Check if this user is the root user (user with ID 1).
     */
    public function isRootUser(): bool
    {
        return $this->exists && $this->id === 1;
    }

    /**
     * Check if the root user's login information should be protected.
     * Only protects in non-production environments.
     */
    public function isRootUserProtected(): bool
    {
        return $this->isRootUser() && !app()->environment('production');
    }

    /**
     * Check if this user can be deleted.
     * Root user can NEVER be deleted in any environment.
     */
    public function canBeDeleted(): bool
    {
        return !$this->isRootUser();
    }

    /**
     * Override role assignment methods to protect root user.
     */
    public function assignRole(...$roles): static
    {
        if ($this->isRootUserProtected()) {
            // Silently ignore role assignments for protected root user
            return $this;
        }

        return parent::assignRole(...$roles);
    }

    /**
     * Override role removal methods to protect root user.
     */
    public function removeRole($role): static
    {
        if ($this->isRootUserProtected()) {
            // Silently ignore role removals for protected root user
            return $this;
        }

        return parent::removeRole($role);
    }

    /**
     * Override role syncing to protect root user.
     */
    public function syncRoles(...$roles): static
    {
        if ($this->isRootUserProtected()) {
            // Silently ignore role syncing for protected root user
            return $this;
        }

        return parent::syncRoles(...$roles);
    }

    /**
     * Override permission assignment to protect root user.
     */
    public function givePermissionTo(...$permissions): static
    {
        if ($this->isRootUserProtected()) {
            // Silently ignore permission assignments for protected root user
            return $this;
        }

        return parent::givePermissionTo(...$permissions);
    }

    /**
     * Override permission revocation to protect root user.
     */
    public function revokePermissionTo($permission): static
    {
        if ($this->isRootUserProtected()) {
            // Silently ignore permission revocations for protected root user
            return $this;
        }

        return parent::revokePermissionTo($permission);
    }

    /**
     * Override permission syncing to protect root user.
     */
    public function syncPermissions(...$permissions): static
    {
        if ($this->isRootUserProtected()) {
            // Silently ignore permission syncing for protected root user
            return $this;
        }

        return parent::syncPermissions(...$permissions);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can_login;
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->allTeams();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->belongsToTeam($tenant);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function isVirtual(): bool
    {
        return !$this->can_login;
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TerritoryAssignment::class, 'assignee_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasMany
    {
        return $this->hasMany(Contact::class)->where('is_primary', true);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(Contact::class)->where('type', ContactType::EMERGENCY);
    }

    /**
     * Get the user's preferred locale.
     */
    public function preferredLocale(): string
    {
        Log::debug('User preferred locale', [
            'user' => $this->fullName,
            '$this->locale' => $this->locale, 
            'app()->getLocale()' => app()->getLocale(),
            'config("app.locale")' => config('app.locale'),
            'config("app.fallback_locale")' => config('app.fallback_locale'),
            'env("APP_LOCALE")' => env('APP_LOCALE', 'unknown'),
        ]);
        return $this->locale ?? app()->getLocale();
    }
}
