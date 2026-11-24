<?php

namespace App\Traits;

use App\Models\SharedUrl;
use Illuminate\Auth\Access\AuthorizationException;
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
     * Whether the model can be shared (and its link opened): not once it is in the trash.
     */
    public function isShareable(): bool
    {
        return ! (method_exists($this, 'trashed') && $this->trashed());
    }

    /**
     * Share the model and return the shared URL.
     *
     * If a shared URL already exists, it will return that.
     * Otherwise, it will create a new shared URL.
     *
     * @throws AuthorizationException While sharing is disabled from the territory settings, or the model cannot be shared.
     */
    public function share(): SharedUrl
    {
        if (! SharedUrl::isSharingEnabled()) {
            throw new AuthorizationException('Sharing is disabled.');
        }

        if (! $this->isShareable()) {
            throw new AuthorizationException('This resource cannot be shared.');
        }

        $this->load('shared');

        if ($this->shared !== null) {
            return $this->shared;
        }

        // A model has one link at most (unique index): when two shares race, the one that loses gets the other's link
        $sharedUrl = $this->shared()->createOrFirst([], [
            'uid' => Str::uuid()->toString(),
        ]);

        $this->setRelation('shared', $sharedUrl);

        return $sharedUrl;
    }
}
