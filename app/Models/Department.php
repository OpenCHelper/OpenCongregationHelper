<?php

namespace App\Models;

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A department of the congregation, with the users serving in it.
 *
 * The departments are defined by `DepartmentKey` (one row each, created by the migrations): only their members
 * can be changed.
 */
class Department extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => DepartmentKey::class,
        ];
    }

    /**
     * Get the department with the given key.
     */
    public static function for(DepartmentKey $key): self
    {
        return static::query()->firstOrCreate(['key' => $key]);
    }

    /**
     * Sort the departments as their keys are declared.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        $keys = array_map(static fn (DepartmentKey $key): string => $key->value, DepartmentKey::cases());

        $cases = collect($keys)
            ->map(fn (string $key, int $index): string => "when '{$key}' then {$index}")
            ->implode(' ');

        // Wrapped by the grammar, which adds the table prefix (e.g. of the previews) that raw SQL would miss
        $column = $query->getQuery()->getGrammar()->wrap($this->qualifyColumn('key'));

        return $query->orderByRaw("case {$column} {$cases} else ".count($keys).' end');
    }

    public function getLabel(): string
    {
        return $this->key->getLabel();
    }

    /**
     * Get the users serving in the department.
     *
     * @return BelongsToMany<User, $this, DepartmentMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(DepartmentMember::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * Get the overseers of the department.
     *
     * @return BelongsToMany<User, $this, DepartmentMember>
     */
    public function overseers(): BelongsToMany
    {
        return $this->members()->wherePivot('role', DepartmentRole::Overseer->value);
    }

    /**
     * Check if the given user is an overseer of the department.
     */
    public function isOverseer(User $user): bool
    {
        return $this->overseers()->whereKey($user->getKey())->exists();
    }

    /**
     * Check if the given user is the only overseer of the department: they cannot be demoted or removed until
     * another overseer is appointed.
     */
    public function isOnlyOverseer(User $user): bool
    {
        return $this->wouldLoseEveryOverseer([$user->getKey()]);
    }

    /**
     * Check if demoting or removing the given users would leave the department without an overseer: once it has
     * one, a department must always keep at least one.
     *
     * @param  iterable<int|string>  $userIds
     */
    public function wouldLoseEveryOverseer(iterable $userIds): bool
    {
        $userIds = collect($userIds)->all();

        return $this->overseers()->whereKey($userIds)->exists()
            && $this->overseers()->whereKeyNot($userIds)->doesntExist();
    }
}
