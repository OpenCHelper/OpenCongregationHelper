@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div
    x-data
    x-init="$wire.on('loggedOut', () => new FilamentNotification().title(@js(__('profile.browser_sessions.logged_out'))).success().send())"
>
    <x-filament::section
        aside
        :heading="__('profile.browser_sessions.title')"
        :description="__('profile.browser_sessions.description')"
    >
        <div class="grid gap-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('profile.browser_sessions.explanation') }}
            </p>

            @if (count($this->sessions) > 0)
                <ul class="grid gap-4">
                    @foreach ($this->sessions as $session)
                        <li class="flex items-center gap-3">
                            <x-filament::icon
                                :icon="$session->agent->isDesktop() ? Heroicon::OutlinedComputerDesktop : Heroicon::OutlinedDevicePhoneMobile"
                                class="size-8 shrink-0 text-gray-400 dark:text-gray-500"
                            />

                            <div class="grid gap-y-1">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $session->agent->platform() ?: __('profile.browser_sessions.unknown') }}
                                    &ndash;
                                    {{ $session->agent->browser() ?: __('profile.browser_sessions.unknown') }}
                                </span>

                                <span class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $session->ip_address }}

                                    @if ($session->is_current_device)
                                        <x-filament::badge color="success" size="sm">
                                            {{ __('profile.browser_sessions.this_device') }}
                                        </x-filament::badge>
                                    @else
                                        <span>&middot; {{ __('profile.browser_sessions.last_active') }} {{ $session->last_active }}</span>
                                    @endif
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-slot name="footer">
            <x-filament::button
                :icon="Heroicon::ArrowRightStartOnRectangle"
                wire:click="confirmLogout"
            >
                {{ __('profile.browser_sessions.logout_other_sessions') }}
            </x-filament::button>
        </x-slot>
    </x-filament::section>

    {{-- The modal follows `$confirmingLogout`, which Jetstream toggles on the server. --}}
    <x-filament::modal
        id="confirm-logout-other-browser-sessions"
        :icon="Heroicon::OutlinedArrowRightStartOnRectangle"
        :heading="__('profile.browser_sessions.logout_other_sessions')"
        :description="__('profile.browser_sessions.confirm_password')"
        alignment="center"
        footer-actions-alignment="center"
        width="md"
        x-effect="if ($wire.confirmingLogout) { isOpen || open() } else if (isOpen) { close() }"
        x-on:modal-closed="$wire.confirmingLogout = false"
        wire:submit.prevent="logoutOtherBrowserSessions"
    >
        <x-filament-forms::field-wrapper
            id="logout_other_browser_sessions_password"
            :label="__('profile.browser_sessions.password_placeholder')"
            label-sr-only
            state-path="password"
        >
            <x-filament::input.wrapper :valid="! $errors->has('password')">
                <x-filament::input
                    id="logout_other_browser_sessions_password"
                    type="password"
                    autocomplete="current-password"
                    :placeholder="__('profile.browser_sessions.password_placeholder')"
                    wire:model="password"
                    x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $el.focus(), 250)"
                />
            </x-filament::input.wrapper>
        </x-filament-forms::field-wrapper>

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="close()">
                {{ __('profile.browser_sessions.cancel') }}
            </x-filament::button>

            <x-filament::button type="submit" form="logoutOtherBrowserSessions">
                {{ __('profile.browser_sessions.logout_other_sessions') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
