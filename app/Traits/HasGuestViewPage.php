<?php

namespace App\Traits;

/**
 * Trait to provide a specific guest view page route for shareable resources.
 * 
 * If not used, by default a generic guest page route will be generated.
 */
trait HasGuestViewPage
{
    /**
     * Override this property in your model to customize the guest page route.
     * Example: protected ?string $guestPageRoute = '/guest/shared-assignment';
     */
    // protected ?string $guestPageRoute = null;

    /**
     * Get the guest page route for this shareable resource.
     */
    public function getGuestPageRoute(): string
    {
        return property_exists($this, 'guestPageRoute') && isset($this->guestPageRoute) 
            ? $this->guestPageRoute 
            : $this->getDefaultGuestPageRoute();
    }

    /**
     * Get the default guest page route based on the model class name.
     */
    protected function getDefaultGuestPageRoute(): string
    {
        return "/guest/shared-" . str(class_basename($this))->kebab();
    }
}
