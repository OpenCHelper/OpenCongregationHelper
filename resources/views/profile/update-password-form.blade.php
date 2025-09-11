<x-form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('profile.update_password.title') }}
    </x-slot>

    <x-slot name="description">
        {{ __('profile.update_password.description') }}
    </x-slot>

    <x-slot name="form">
        @if(auth()->user()->isRootUserProtected())
            <div class="col-span-6">
                <div class="rounded-md bg-yellow-50 dark:bg-yellow-900/20 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                {{ __('profile.root_user_protection.password_change_not_allowed') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="col-span-6 sm:col-span-4">
                <x-label for="current_password" value="{{ __('profile.update_password.current_password') }}" />
                <x-input id="current_password" type="password" class="mt-1 block w-full" wire:model="state.current_password" autocomplete="current-password" />
                <x-input-error for="current_password" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-label for="password" value="{{ __('profile.update_password.new_password') }}" />
                <x-input id="password" type="password" class="mt-1 block w-full" wire:model="state.password" autocomplete="new-password" />
                <x-input-error for="password" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-label for="password_confirmation" value="{{ __('profile.update_password.confirm_password') }}" />
                <x-input id="password_confirmation" type="password" class="mt-1 block w-full" wire:model="state.password_confirmation" autocomplete="new-password" />
                <x-input-error for="password_confirmation" class="mt-2" />
            </div>
        @endif
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('profile.update_password.saved') }}
        </x-action-message>

        @if(!auth()->user()->isRootUserProtected())
            <x-button>
                {{ __('profile.update_password.save') }}
            </x-button>
        @endif
    </x-slot>
</x-form-section>
