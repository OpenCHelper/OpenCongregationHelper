<form
    wire:submit="updatePassword"
    x-data
    x-init="$wire.on('saved', () => new FilamentNotification().title(@js(__('profile.update_password.saved'))).success().send())"
>
    <x-filament::section
        aside
        :heading="__('profile.update_password.title')"
        :description="__('profile.update_password.description')"
    >
        @if (auth()->user()->isRootUserProtected())
            <x-filament::callout
                color="warning"
                :icon="Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle"
                :description="__('profile.root_user_protection.password_change_not_allowed')"
            />
        @else
            <div class="grid gap-6">
                <x-filament-forms::field-wrapper
                    id="current_password"
                    :label="__('profile.update_password.current_password')"
                    required
                    state-path="current_password"
                >
                    <x-filament::input.wrapper :valid="! $errors->has('current_password')">
                        <x-filament::input
                            id="current_password"
                            type="password"
                            wire:model="state.current_password"
                            autocomplete="current-password"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper
                    id="password"
                    :label="__('profile.update_password.new_password')"
                    required
                    state-path="password"
                >
                    <x-filament::input.wrapper :valid="! $errors->has('password')">
                        <x-filament::input
                            id="password"
                            type="password"
                            wire:model="state.password"
                            autocomplete="new-password"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper
                    id="password_confirmation"
                    :label="__('profile.update_password.confirm_password')"
                    required
                    state-path="password_confirmation"
                >
                    <x-filament::input.wrapper :valid="! $errors->has('password_confirmation')">
                        <x-filament::input
                            id="password_confirmation"
                            type="password"
                            wire:model="state.password_confirmation"
                            autocomplete="new-password"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
            </div>

            <x-slot name="footer">
                <x-filament::button type="submit" form="updatePassword">
                    {{ __('profile.update_password.save') }}
                </x-filament::button>
            </x-slot>
        @endif
    </x-filament::section>
</form>
