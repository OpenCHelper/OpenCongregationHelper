<x-filament-panels::page>
    @if (Laravel\Fortify\Features::canUpdateProfileInformation())
        @livewire(Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm::class)

        @livewire('profile-contacts')
    @endif

    {{-- Opens the offer to install the app (`filament.pwa-install-prompt`): shown only when it can be or is installed --}}
    <div x-data x-show="$store.pwaInstall.available || $store.pwaInstall.installed" x-cloak class="app-pwa-install-section">
        <x-filament::section
            aside
            :heading="__('pwa.install.profile.title')"
            :description="__('pwa.install.profile.description')"
        >
            <p x-show="$store.pwaInstall.installed" class="flex items-center gap-2 text-sm font-medium text-success-600 dark:text-success-400">
                <x-filament::icon icon="heroicon-o-check-circle" class="size-5" />
                {{ __('pwa.install.profile.installed') }}
            </p>

            <x-filament::button
                x-show="$store.pwaInstall.available && ! $store.pwaInstall.installed"
                x-on:click="$dispatch('open-pwa-install')"
                icon="heroicon-o-arrow-down-tray"
            >
                {{ __('pwa.install.action') }}
            </x-filament::button>
        </x-filament::section>
    </div>

    @livewire('profile-push-notifications')

    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
        @livewire(Laravel\Jetstream\Http\Livewire\UpdatePasswordForm::class)
    @endif

    @if (Filament\Facades\Filament::hasMultiFactorAuthentication())
        <x-filament::section
            aside
            :heading="__('profile.two_factor_authentication.title')"
            :description="__('profile.two_factor_authentication.description')"
        >
            {{ $this->multiFactorAuthentication }}
        </x-filament::section>
    @endif

    @livewire(Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm::class)

    @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
        @livewire(Laravel\Jetstream\Http\Livewire\DeleteUserForm::class)
    @endif
</x-filament-panels::page>
