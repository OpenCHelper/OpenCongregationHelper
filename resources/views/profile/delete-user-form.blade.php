@php
    use Filament\Support\Icons\Heroicon;

    // The root user, a user who has assignments and the last super administrator who can log in cannot be deleted (see
    // `UserObserver::guardDeletion()`): the form only explains it
    $canBeDeleted = ! auth()->user()->isRootUser() && ! auth()->user()->hasAssignments() && ! auth()->user()->isLastSuperAdmin();
@endphp

<div>
    <x-filament::section
        aside
        :heading="__('profile.delete_account.title')"
        :description="__('profile.delete_account.description')"
    >
        @if (auth()->user()->isRootUser())
            <x-filament::callout
                color="danger"
                :icon="Heroicon::OutlinedNoSymbol"
                :description="__('profile.root_user_protection.account_deletion_not_allowed')"
            />
        @elseif (auth()->user()->hasAssignments())
            <x-filament::callout
                color="warning"
                :icon="Heroicon::OutlinedNoSymbol"
                :description="__('profile.delete_account.has_assignments')"
            />
        @elseif (auth()->user()->isLastSuperAdmin())
            <x-filament::callout
                color="warning"
                :icon="Heroicon::OutlinedNoSymbol"
                :description="__('profile.delete_account.last_super_admin')"
            />
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('profile.delete_account.warning') }}
            </p>

            <x-slot name="footer">
                <x-filament::button
                    color="danger"
                    :icon="Heroicon::Trash"
                    wire:click="confirmUserDeletion"
                >
                    {{ __('profile.delete_account.delete_button') }}
                </x-filament::button>
            </x-slot>
        @endif
    </x-filament::section>

    @if ($canBeDeleted)
        {{-- The modal follows `$confirmingUserDeletion`, which Jetstream toggles on the server. --}}
        <x-filament::modal
            id="confirm-user-deletion"
            :icon="Heroicon::OutlinedTrash"
            icon-color="danger"
            :heading="__('profile.delete_account.title')"
            :description="__('profile.delete_account.confirm_deletion')"
            alignment="center"
            footer-actions-alignment="center"
            width="md"
            x-effect="if ($wire.confirmingUserDeletion) { isOpen || open() } else if (isOpen) { close() }"
            x-on:modal-closed="$wire.confirmingUserDeletion = false"
            wire:submit.prevent="deleteUser"
        >
            <x-filament-forms::field-wrapper
                id="delete_user_password"
                :label="__('profile.delete_account.password_placeholder')"
                label-sr-only
                state-path="password"
            >
                <x-filament::input.wrapper :valid="! $errors->has('password')">
                    <x-filament::input
                        id="delete_user_password"
                        type="password"
                        autocomplete="current-password"
                        :placeholder="__('profile.delete_account.password_placeholder')"
                        wire:model="password"
                        x-on:confirming-delete-user.window="setTimeout(() => $el.focus(), 250)"
                    />
                </x-filament::input.wrapper>
            </x-filament-forms::field-wrapper>

            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="close()">
                    {{ __('profile.delete_account.cancel') }}
                </x-filament::button>

                <x-filament::button type="submit" color="danger" form="deleteUser">
                    {{ __('profile.delete_account.delete_button') }}
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</div>
