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
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasParentSoftDeletable()
    {
        // Took inspiration from [here](https://yashkumarprasad14.medium.com/preventing-child-record-display-when-parent-is-soft-deleted-using-laravel-scopes-2475449cc867)
        static::addGlobalScope('parentNotDeleted', function (Builder $builder) {
            $class = new ReflectionClass(static::class);
            $parents = array_filter($class->getMethods(ReflectionMethod::IS_PUBLIC), function ($method) {
                return $method->class === static::class && $method->getReturnType() == BelongsTo::class;
            });

            foreach ($parents as $parent) {
                $builder->whereHas($parent->getName(), function (Builder $query) {
                    $query->whereNull('deleted_at');
                });
            }
        });
    }
}
