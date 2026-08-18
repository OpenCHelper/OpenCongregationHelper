<?php

namespace App\Traits;

use App\Models\SharedUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

trait HasSharedUrl
{
    /**
     * Get the shared URL for the model.
     */
    public function shared(): MorphOne
    {
        return $this->morphOne(SharedUrl::class, 'shareable');
    }

    /**
     * Get the URL for the model.
     */
    public function sharedUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->shared ? $this->shared->uid : null,
        );
    }

    /**
     * Share the model and return the shared URL.
     *
     * If a shared URL already exists, it will return that.
     * Otherwise, it will create a new shared URL.
     */
    public function share(): SharedUrl
    {
        $this->load('shared');

        if ($this->shared?->exists()) {
            return $this->shared;
        }

        $sharedUrl = $this->shared()->firstOrCreate([
            'uid' => Str::uuid()->toString(),
        ]);

        return $sharedUrl;
    }
}
