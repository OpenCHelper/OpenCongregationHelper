<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('profile.update_profile_information.title') }}
    </x-slot>

    <x-slot name="description">
        {{ __('profile.update_profile_information.description') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('profile.update_profile_information.photo_label') }}" />

                <!-- Current Profile Photo -->
                <div class="mt-2" x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->full_name }}" class="rounded-full size-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('profile.update_profile_information.select_new_photo') }}
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('profile.update_profile_information.remove_photo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- First Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="first_name" value="{{ __('profile.update_profile_information.first_name') }}" />
            <x-input id="first_name" type="text" class="mt-1 block w-full" wire:model="state.first_name" required autocomplete="given-name" />
            <x-input-error for="first_name" class="mt-2" />
        </div>

        <!-- Last Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="last_name" value="{{ __('profile.update_profile_information.last_name') }}" />
            <x-input id="last_name" type="text" class="mt-1 block w-full" wire:model="state.last_name" required autocomplete="family-name" />
            <x-input-error for="last_name" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="email" value="{{ __('profile.update_profile_information.email') }}" />
            <x-input 
                id="email" 
                type="email" 
                class="mt-1 block w-full" 
                wire:model="state.email" 
                required 
                autocomplete="username" 
                @if($this->user->isRootUserProtected()) disabled @endif 
            />
            <x-input-error for="email" class="mt-2" />

            @if($this->user->isRootUserProtected())
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
                                    {{ __('profile.root_user_protection.generic_field_disabled') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <p class="text-sm mt-2 dark:text-white">
                    {{ __('profile.update_profile_information.email_unverified') }}

                    <button type="button" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" wire:click.prevent="sendEmailVerification">
                        {{ __('profile.update_profile_information.resend_verification') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                        {{ __('profile.update_profile_information.verification_sent') }}
                    </p>
                @endif
            @endif
        </div>

        <!-- User preferred locale -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="locale" value="{{ __('profile.update_profile_information.preferred_language') }}" />
            
            <select id="locale" class="mt-1 block w-full" wire:model="state.locale">
                <option value="">{{ __('profile.update_profile_information.select_language') }}</option>
                @foreach (config('app.available_locales') as $localeShort => $localeLong)
                    <option 
                        value="{{ $localeShort }}" 
                        @selected($localeShort == $this->user->locale)
                    >{{ $localeLong }} ({{ $localeShort }})</option>
                @endforeach
            </select>

            <x-input-error for="locale" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('profile.update_profile_information.saved') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('profile.update_profile_information.save') }}
        </x-button>
    </x-slot>
</x-form-section>
