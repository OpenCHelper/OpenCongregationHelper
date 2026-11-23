{{--
    Push notifications of the current device (see `resources/js/push-subscription.js`).
--}}
<div>
    @if ($canSubscribe)
        <x-filament::section
            aside
            :heading="__('profile.push_notifications.title')"
            :description="__('profile.push_notifications.description')"
        >
            <div
                x-data="{
                    ...pushSubscription(@js($publicKey)),
                    init() {
                        return this.refreshStatus();
                    },
                }"
                class="app-push-notifications space-y-4 text-sm text-gray-600 dark:text-gray-400"
            >
                <p x-show="status === 'enabled'" x-cloak class="flex items-center gap-2 font-medium text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="size-5" />
                    {{ __('profile.push_notifications.enabled') }}
                </p>

                {{-- Android lets each site or app show its notifications on screen only once allowed in its settings --}}
                <p x-show="status === 'enabled'" x-cloak>
                    {{ __('profile.push_notifications.pop_up_tip') }}
                </p>

                <p x-show="status === 'disabled'" x-cloak>
                    {{ __('profile.push_notifications.disabled') }}
                </p>

                <p x-show="status === 'denied'" x-cloak class="text-warning-600 dark:text-warning-400">
                    {{ __('profile.push_notifications.denied') }}
                </p>

                <p x-show="status === 'install'" x-cloak>
                    {{ __('profile.push_notifications.ios_install') }}
                </p>

                <p x-show="status === 'unsupported'" x-cloak>
                    {{ __('profile.push_notifications.unsupported') }}
                </p>

                <p x-show="status === 'error'" x-cloak class="text-danger-600 dark:text-danger-400">
                    {{ __('profile.push_notifications.error') }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button
                        x-show="status === 'disabled' || status === 'error'"
                        x-cloak
                        x-on:click="enable()"
                        x-bind:disabled="busy"
                        icon="heroicon-o-bell"
                    >
                        {{ __('profile.push_notifications.enable') }}
                    </x-filament::button>

                    <x-filament::button
                        x-show="status === 'enabled'"
                        x-cloak
                        wire:click="sendTest"
                        icon="heroicon-o-paper-airplane"
                        color="gray"
                    >
                        {{ __('profile.push_notifications.send_test') }}
                    </x-filament::button>

                    <x-filament::button
                        x-show="status === 'enabled'"
                        x-cloak
                        x-on:click="disable()"
                        x-bind:disabled="busy"
                        icon="heroicon-o-bell-slash"
                        color="danger"
                        outlined
                    >
                        {{ __('profile.push_notifications.disable') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</div>
