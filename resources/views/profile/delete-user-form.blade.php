<x-action-section>
    <x-slot name="title">
        {{ __('profile.delete_account.title') }}
    </x-slot>

    <x-slot name="description">
        {{ __('profile.delete_account.description') }}
    </x-slot>

    <x-slot name="content">
        @if(auth()->user()->isRootUserProtected())
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700 dark:text-red-200">
                            {{ __('profile.root_user_protection.account_deletion_not_allowed') }}
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
                {{ __('profile.delete_account.warning') }}
            </div>

            <div class="mt-5">
                <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                    {{ __('profile.delete_account.delete_button') }}
                </x-danger-button>
            </div>
        @endif

        @if(!auth()->user()->isRootUserProtected())
            <!-- Delete User Confirmation Modal -->
            <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title">
                {{ __('profile.delete_account.title') }}
            </x-slot>

            <x-slot name="content">
                {{ __('profile.delete_account.confirm_deletion') }}

                <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password" class="mt-1 block w-3/4"
                                autocomplete="current-password"
                                placeholder="{{ __('profile.delete_account.password_placeholder') }}"
                                x-ref="password"
                                wire:model="password"
                                wire:keydown.enter="deleteUser" />

                    <x-input-error for="password" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    {{ __('profile.delete_account.cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3" wire:click="deleteUser" wire:loading.attr="disabled">
                    {{ __('profile.delete_account.delete_button') }}
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
        @endif
    </x-slot>
</x-action-section>
