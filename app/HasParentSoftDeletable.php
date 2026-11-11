<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ReflectionClass;
use ReflectionMethod;

/**
 * Automatically apply a global scope to the model to filter out soft-deleted parent models.
 */
trait HasParentSoftDeletable
{
    /**
     * The names of the parent (`BelongsTo`) relationships, cached per model class.
     *
     * @var array<class-string, list<string>>
     */
    protected static array $parentRelationshipNames = [];

    /**
     * Boot the trait.
     */
    public static function bootHasParentSoftDeletable(): void
    {
        // Took inspiration from [here](https://yashkumarprasad14.medium.com/preventing-child-record-display-when-parent-is-soft-deleted-using-laravel-scopes-2475449cc867)
        static::addGlobalScope('parentNotDeleted', function (Builder $builder) {
            foreach (static::getParentRelationshipNames() as $relationship) {
                $builder->whereHas($relationship, function (Builder $query) {
                    $query->whereNull('deleted_at');
                });
            }
        });
    }

    /**
     * Get the names of the public `BelongsTo` relationships declared by the model.
     *
     * They are discovered through reflection only once per model class,
     * instead of on every query.
     *
     * @return list<string>
     */
    protected static function getParentRelationshipNames(): array
    {
        return static::$parentRelationshipNames[static::class] ??= collect((new ReflectionClass(static::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->class === static::class
                && (string) $method->getReturnType() === BelongsTo::class)
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();
    }
}
