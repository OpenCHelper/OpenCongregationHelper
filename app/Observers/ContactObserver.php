<?php

namespace App\Observers;

use App\Models\Contact;

class ContactObserver
{
    /**
     * Handle the Contact "saved" event.
     *
     * A user has at most one primary contact: a contact saved as primary replaces the previous one, wherever it is
     * saved from (the profile, the user resource, the API...).
     */
    public function saved(Contact $contact): void
    {
        if (! $contact->is_primary) {
            return;
        }

        Contact::query()
            ->where('user_id', $contact->user_id)
            ->whereKeyNot($contact->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
