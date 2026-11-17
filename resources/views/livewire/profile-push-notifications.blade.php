{{--
    Push notifications of the current device: the browser subscribes with the public VAPID key and the subscription is
    stored for the user, so that the service worker (`public/sw.js`) shows the notifications even with the app closed.

    On iOS and iPadOS the push notifications are available only once the app is added to the Home Screen.
--}}
<div>
    @if ($isConfigured)
        <x-filament::section
            aside
            :heading="__('profile.push_notifications.title')"
            :description="__('profile.push_notifications.description')"
        >
            <div
                x-data="{
                    publicKey: @js($publicKey),
                    status: 'loading',
                    busy: false,
                    async init() {
                        if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
                            this.status = this.isIos() && ! this.isInstalled() ? 'install' : 'unsupported';

                            return;
                        }

                        if (Notification.permission === 'denied') {
                            this.status = 'denied';

                            return;
                        }

                        try {
                            const subscription = await this.currentSubscription();

                            if (! subscription || ! await $wire.ownsSubscription(subscription.endpoint)) {
                                this.status = 'disabled';

                                return;
                            }

                            // Sent again on every visit, as the browser may have renewed its keys in the meantime.
                            await $wire.subscribe(this.serialize(subscription));
                            this.status = 'enabled';
                        } catch (error) {
                            console.error(error);
                            this.status = 'error';
                        }
                    },
                    isIos() {
                        return /iphone|ipad|ipod/i.test(navigator.userAgent)
                            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                    },
                    isInstalled() {
                        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                    },
                    async currentSubscription() {
                        const registration = await this.serviceWorker();

                        return registration.pushManager.getSubscription();
                    },
                    serviceWorker() {
                        // `ready` never settles when the service worker cannot be registered.
                        return Promise.race([
                            navigator.serviceWorker.ready,
                            new Promise((resolve, reject) => setTimeout(() => reject(new Error('Service worker not ready')), 10000)),
                        ]);
                    },
                    serialize(subscription) {
                        return {
                            ...subscription.toJSON(),
                            // `aes128gcm` is the standard encoding, supported by every browser with the Push API.
                            contentEncoding: PushManager.supportedContentEncodings?.includes('aes128gcm') === false
                                ? PushManager.supportedContentEncodings[0]
                                : 'aes128gcm',
                        };
                    },
                    applicationServerKey() {
                        const base64 = (this.publicKey + '='.repeat((4 - this.publicKey.length % 4) % 4))
                            .replace(/-/g, '+')
                            .replace(/_/g, '/');

                        return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
                    },
                    async enable() {
                        this.busy = true;

                        try {
                            const permission = await Notification.requestPermission();

                            if (permission !== 'granted') {
                                this.status = permission === 'denied' ? 'denied' : 'disabled';

                                return;
                            }

                            const registration = await this.serviceWorker();
                            const subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: this.applicationServerKey(),
                            });

                            await $wire.subscribe(this.serialize(subscription));
                            this.status = 'enabled';
                        } catch (error) {
                            console.error(error);
                            this.status = 'error';
                        } finally {
                            this.busy = false;
                        }
                    },
                    async disable() {
                        this.busy = true;

                        try {
                            const subscription = await this.currentSubscription();

                            if (subscription) {
                                await $wire.unsubscribe(subscription.endpoint);
                                await subscription.unsubscribe();
                            }

                            this.status = 'disabled';
                        } catch (error) {
                            console.error(error);
                            this.status = 'error';
                        } finally {
                            this.busy = false;
                        }
                    },
                }"
                class="app-push-notifications space-y-4 text-sm text-gray-600 dark:text-gray-400"
            >
                <p x-show="status === 'enabled'" x-cloak class="flex items-center gap-2 font-medium text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="size-5" />
                    {{ __('profile.push_notifications.enabled') }}
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
