<form
    wire:submit="updateProfileInformation"
    x-data
    {{-- Jetstream refreshes its own navigation menu, so refresh the topbar of Filament instead, with the name and the photo of the user. --}}
    x-init="
        $wire.on('saved', () => new FilamentNotification().title(@js(__('profile.update_profile_information.saved'))).success().send());
        $wire.on('refresh-navigation-menu', () => Livewire.dispatch('refresh-topbar'));
    "
    {{-- Keep the save button disabled while the new photo is being uploaded, as Filament does with its own file uploads. --}}
    x-on:livewire-upload-start="$dispatch('form-processing-started', { message: @js(__('profile.update_profile_information.save')) })"
    x-on:livewire-upload-finish="$dispatch('form-processing-finished')"
    x-on:livewire-upload-error="$dispatch('form-processing-finished')"
    x-on:livewire-upload-cancel="$dispatch('form-processing-finished')"
>
    <x-filament::section
        aside
        :heading="__('profile.update_profile_information.title')"
        :description="__('profile.update_profile_information.description')"
    >
        <div class="grid gap-6">
            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                <x-filament-forms::field-wrapper
                    id="photo"
                    :label="__('profile.update_profile_information.photo_label')"
                    state-path="photo"
                >
                    <div x-data="{ photoPreview: null }" class="flex flex-wrap items-center gap-4">
                        <input
                            type="file"
                            id="photo"
                            accept=".jpg,.jpeg,.png"
                            class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                const reader = new FileReader();
                                reader.onload = (e) => { photoPreview = e.target.result };
                                reader.readAsDataURL($refs.photo.files[0]);
                            "
                        />

                        <img
                            x-show="! photoPreview"
                            src="{{ filament()->getUserAvatarUrl($this->user) }}"
                            alt="{{ $this->user->full_name }}"
                            class="size-16 rounded-full object-cover"
                        />

                        <img
                            x-cloak
                            x-show="photoPreview"
                            x-bind:src="photoPreview"
                            alt="{{ $this->user->full_name }}"
                            class="size-16 rounded-full object-cover"
                        />

                        <div class="flex flex-wrap gap-3">
                            <x-filament::button
                                color="gray"
                                size="sm"
                                :icon="Filament\Support\Icons\Heroicon::ArrowUpTray"
                                wire:target="photo"
                                x-on:click.prevent="$refs.photo.click()"
                            >
                                {{ __('profile.update_profile_information.select_new_photo') }}
                            </x-filament::button>

                            @if ($this->user->profile_photo_path)
                                <x-filament::button
                                    color="danger"
                                    size="sm"
                                    outlined
                                    :icon="Filament\Support\Icons\Heroicon::Trash"
                                    wire:click="deleteProfilePhoto"
                                >
                                    {{ __('profile.update_profile_information.remove_photo') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </x-filament-forms::field-wrapper>
            @endif

            <x-filament-forms::field-wrapper
                id="first_name"
                :label="__('profile.update_profile_information.first_name')"
                required
                state-path="first_name"
            >
                <x-filament::input.wrapper :valid="! $errors->has('first_name')">
                    <x-filament::input
                        id="first_name"
                        type="text"
                        wire:model="state.first_name"
                        required
                        autocomplete="given-name"
                    />
                </x-filament::input.wrapper>
            </x-filament-forms::field-wrapper>

            <x-filament-forms::field-wrapper
                id="last_name"
                :label="__('profile.update_profile_information.last_name')"
                required
                state-path="last_name"
            >
                <x-filament::input.wrapper :valid="! $errors->has('last_name')">
                    <x-filament::input
                        id="last_name"
                        type="text"
                        wire:model="state.last_name"
                        required
                        autocomplete="family-name"
                    />
                </x-filament::input.wrapper>
            </x-filament-forms::field-wrapper>

            <x-filament-forms::field-wrapper
                id="email"
                :label="__('profile.update_profile_information.email')"
                :required="! $this->user->isRootUserProtected()"
                state-path="email"
            >
                <div class="grid gap-y-2">
                    <x-filament::input.wrapper
                        :disabled="$this->user->isRootUserProtected()"
                        :prefix-icon="Filament\Support\Icons\Heroicon::OutlinedEnvelope"
                        :valid="! $errors->has('email')"
                    >
                        <x-filament::input
                            id="email"
                            type="email"
                            wire:model="state.email"
                            required
                            autocomplete="username"
                            :disabled="$this->user->isRootUserProtected()"
                        />
                    </x-filament::input.wrapper>

                    @if ($this->user->isRootUserProtected())
                        <x-filament::callout
                            color="warning"
                            :icon="Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle"
                            :description="__('profile.root_user_protection.generic_field_disabled')"
                        />
                    @endif

                    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('profile.update_profile_information.email_unverified') }}

                            <x-filament::link tag="button" size="sm" wire:click.prevent="sendEmailVerification">
                                {{ __('profile.update_profile_information.resend_verification') }}
                            </x-filament::link>
                        </p>

                        @if ($this->verificationLinkSent)
                            <p class="text-sm font-medium text-success-600 dark:text-success-400">
                                {{ __('profile.update_profile_information.verification_sent') }}
                            </p>
                        @endif
                    @endif
                </div>
            </x-filament-forms::field-wrapper>

            <x-filament-forms::field-wrapper
                id="locale"
                :label="__('profile.update_profile_information.preferred_language')"
                state-path="locale"
            >
                <x-filament::input.wrapper
                    :prefix-icon="Filament\Support\Icons\Heroicon::OutlinedLanguage"
                    :valid="! $errors->has('locale')"
                >
                    <x-filament::input.select id="locale" wire:model="state.locale">
                        <option value="">{{ __('profile.update_profile_information.select_language') }}</option>

                        @foreach (config('app.available_locales') as $localeShort => $localeLong)
                            <option value="{{ $localeShort }}">{{ $localeLong }} ({{ $localeShort }})</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-filament-forms::field-wrapper>
        </div>

        <x-slot name="footer">
            <x-filament::button type="submit" form="updateProfileInformation">
                {{ __('profile.update_profile_information.save') }}
            </x-filament::button>
        </x-slot>
    </x-filament::section>
</form>
